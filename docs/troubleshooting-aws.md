# AWS Troubleshooting & Ops Notes

Lessons learned from debugging the infra in `infra/scheduling/`. Grouped by topic.

Account-specific identifiers (account ID, EC2 instance ID, RDS identifier) are kept out of
this file for the same reason `*.tfvars` is gitignored — every command below shows how to
resolve them. Region is `ap-northeast-2` throughout.

---

## Scheduled start/stop Lambdas: the DB never stopped

> **Status:** root cause confirmed 2026-08-07. The fix (below) was identified but had not yet
> been applied at the time of writing — re-verify before trusting this section end to end.

### Symptom

The nightly scheduler appeared to stop the EC2 instance but never the RDS instance. The
`chill-stop-resources` Lambda produced no output in the CloudWatch Logs console at all, even
when browsing as the root user.

### What was actually happening

Neither resource was being stopped. `chill-stop-resources` had **never executed once** since
it was created.

The belief that EC2 was stopping came from reading the handler rather than the infrastructure.
`stop.py` called `stop_instances()` on line 7 and `stop_db_instance()` on line 8 with no error
handling, so *any* RDS failure would leave the EC2 stopped and the database running. That is
the shape the symptom took — but it was never reached, because the function never ran.

Evidence that nothing was stopping:

```bash
# Unbroken hourly datapoints across every 01:00 stop window = never stopped.
aws cloudwatch get-metric-statistics --namespace AWS/EC2 --metric-name CPUUtilization \
  --dimensions Name=InstanceId,Value=<instance-id> \
  --start-time 2026-08-05T00:00:00Z --end-time 2026-08-07T09:00:00Z \
  --period 3600 --statistics Average --region ap-northeast-2 \
  --query 'sort_by(Datapoints,&Timestamp)[].Timestamp' --output text

# LaunchTime resets on every start-from-stopped. A stale value = never cycled.
aws ec2 describe-instances --instance-ids <instance-id> --region ap-northeast-2 \
  --query 'Reservations[0].Instances[0].{State:State.Name,LaunchTime:LaunchTime}'
```

The corroborating signal was in the **start** function's logs, every morning for seven days:

```
[ERROR] InvalidDBInstanceStateFault: An error occurred (InvalidDBInstanceState) when calling
the StartDBInstance operation: Instance <db-id> cannot be started as it is not in one of the
following statuses: 'stopped, ...'
```

RDS was still `available` at 08:00, which only happens if the 01:00 stop never landed.

### Root cause: a stale KMS grant

Lambda encrypts environment variables at rest with `alias/aws/lambda` and creates **one KMS
grant per function** so the execution role can decrypt them when the runtime boots.

```bash
KEY_ID=$(aws kms describe-key --key-id alias/aws/lambda --region ap-northeast-2 \
  --query KeyMetadata.KeyId --output text)

aws kms list-grants --key-id "$KEY_ID" --region ap-northeast-2 \
  --query 'Grants[].{Id:GrantId,Grantee:GranteePrincipal,Created:CreationDate}'
```

The two functions shared one execution role, but their grants did not match:

| Function | Grantee principal |
|---|---|
| `chill-start-resources` | `arn:aws:sts::<account>:assumed-role/chill-scheduler-lambda-role/chill-start-resources` |
| `chill-stop-resources` | `AROAQF2KSV5I3QSB4YHYF:chill-stop-resources` ⚠️ |

The stop grant was pinned to a bare role **unique ID** belonging to a *previous incarnation* of
`chill-scheduler-lambda-role`, not to the current role:

```bash
aws iam get-role --role-name chill-scheduler-lambda-role --query 'Role.RoleId'
# AROAQF2KSV5IW6DRQYQTD  — different from the grantee above
```

The grant creation timestamps show an IAM eventual-consistency race after a destroy/recreate
cycle:

| Time (UTC) | Event |
|---|---|
| `09:12:35` | role recreated |
| `09:12:36` | **stop** function created — grant resolved the role name to the *old* unique ID |
| `09:12:44` | **start** function created — propagation had caught up, grant got a proper ARN |

Eight seconds decided which function worked.

At invoke time the execution role's session no longer matched the grantee, KMS denied the
decrypt, and the invocation died in the service layer before the runtime started.

> ⚠️ A stale grantee **cannot be fixed with an IAM policy.** Grants are a separate
> authorization mechanism. Adding `kms:Decrypt` for the key to the execution role changed
> nothing, and that statement was a no-op anyway — `alias/aws/lambda` is the AWS-managed
> default key, and its key policy already allows the account via `kms:ViaService`.

### Fix

Revoke the stale grant, then force Lambda to issue a fresh one. A code-only update is **not**
enough — the grant is recreated when the function's environment is re-encrypted, so the
function has to be replaced.

```bash
aws kms revoke-grant --key-id "$KEY_ID" --grant-id <stale-grant-id> --region ap-northeast-2

cd infra/scheduling
terraform apply -replace=aws_lambda_function.stop
```

Verify both grantees are now full ARNs, not bare `AROA...` IDs. Grants are eventually
consistent — allow a few minutes before invoking.

### Prevention

The race can recur any time the execution role is destroyed and recreated. The durable fix is
to remove the KMS path entirely: the environment variables here are static, non-secret
identifiers, so passing them through the EventBridge Scheduler target's `Input` payload instead
of an `environment` block means there is no env-var encryption, no grant, and no race.

---

## Reading the signals: `Errors` without `Invocations`

This is the metric signature of an invocation that **fails before the runtime starts**, and it
is worth recognizing on sight. It rules out every code-level explanation immediately.

| Metric | Healthy failure (code threw) | Pre-runtime failure |
|---|---|---|
| `Invocations` | present | **absent entirely** |
| `Duration` | present | **absent entirely** |
| `Errors` | present | present |
| Log streams | created | **none, ever** |

```bash
# If Invocations and Duration don't even appear in this list, the code has never run.
aws cloudwatch list-metrics --namespace AWS/Lambda \
  --dimensions Name=FunctionName,Value=<fn> --region ap-northeast-2 \
  --query 'Metrics[].MetricName'
```

For an async (EventBridge Scheduler) trigger, the full pattern was `AsyncEventsReceived` 1/day,
`Errors` 3/day, `AsyncEventsDropped` 1/day, `Throttles` 0 — one delivery, three attempts
(1 + 2 automatic retries), then the event dropped.

Causes worth checking, in order: a stale or missing **KMS grant** on the env-var key, a bad
execution-role trust policy, VPC/ENI exhaustion, or reserved concurrency set to 0.

---

## An empty log group is not a permissions problem

**Symptom:** a Lambda's log group shows nothing in the console, even as the root user.

**Cause:** Lambda only creates a log stream once the runtime boots. An invocation that dies in
the service layer never writes anything, so the group sits at zero streams and the console
renders it identically to a group you lack permission to read.

Root has full access by definition, so if you are root, an empty group means **empty**.

```bash
aws logs describe-log-groups --log-group-name-prefix /aws/lambda/ --region ap-northeast-2 \
  --query 'logGroups[].{n:logGroupName,bytes:storedBytes}' --output table

aws logs describe-log-streams --log-group-name /aws/lambda/<fn> --region ap-northeast-2 \
  --query 'logStreams'
```

Quick way to rule out region and permissions in one shot: check whether a *sibling* function's
logs are readable in the same console. If one works and the other doesn't, it is not the
console, the region, or your identity.

> Related: `infra/scheduling/lambda.tf` declares the log groups explicitly so Terraform owns
> them. Without that, Lambda auto-creates them on first invoke and the next `apply` fails with
> `ResourceAlreadyExistsException`. Retention is 7 days — older evidence is gone.

---

## Invoking a Lambda manually to see an error that never reaches CloudWatch

A **synchronous** invoke returns the failure in the API response, which is the only way to see
a pre-runtime error. `--log-type Tail` returns the log tail inline.

```bash
aws lambda invoke \
  --function-name <fn> \
  --log-type Tail \
  --region ap-northeast-2 \
  --query LogResult --output text \
  response.json | base64 -d

cat response.json   # error payload
```

Two CLI gotchas that produce an unhelpful invalid-input error:

- **`--payload '{}'`** — CLI v2 expects base64 unless you also pass
  `--cli-binary-format raw-in-base64-out`. If the handler ignores the event, just omit it.
- **The outfile is a positional argument.** It must be a real path; pointing it at `/dev/stdout`
  while also using `--query`/`--output` is fragile.

To check invoke permissions without executing anything:

```bash
aws lambda invoke --function-name <fn> --invocation-type DryRun --region ap-northeast-2 out.json
# StatusCode 204 = permitted
```

Console equivalent: **Lambda → function → Test tab → new event, body `{}` → Test**. Note this
bypasses EventBridge Scheduler entirely, so it tests the function and not the schedule. There
is no "run now" button for EventBridge Scheduler — to exercise the whole chain, temporarily
move the cron a couple of minutes out, apply, watch, then revert.

---

## Handler design for start/stop automation

Three problems in the original handlers, all fixed in `infra/scheduling/functions/`:

**Sequential calls with no error isolation.** EC2 first, RDS second, no `try`. Any RDS failure
silently skipped the database and left a half-stopped environment — the exact shape of the
reported symptom. Each resource now gets its own `try`, with errors collected and re-raised at
the end so the `Errors` metric still fires after *both* calls have been attempted.

**No state guard.** `StartDBInstance` only accepts `stopped`; `StopDBInstance` only accepts
`available`. Calling them in any other state raises `InvalidDBInstanceState` and burns the two
async retries for nothing — this was generating three errors every single morning. Both
handlers now check `DBInstanceStatus` first and skip.

> `rds:DescribeDBInstances` has no resource-level support and can only be granted on `"*"`.

**The 3-second default timeout.** `lambda.tf` never set `timeout`, so both functions ran on the
3s default — tight for a cold start plus two control-plane calls, and RDS was the call on the
wrong side of it. Now `timeout = 30`.

---

## Things to remember

- **Verify the premise before debugging it.** "EC2 stops but RDS doesn't" was the reported
  symptom and it was wrong in a way that would have sent the investigation into RDS permissions
  and instance states. Metrics settled it in one query.
- A function that has never run **has no `Invocations` metric at all** — not a zero. Absence in
  `list-metrics` is itself the signal.
- Two functions sharing an execution role can still differ in **per-function KMS grants**. When
  identical config behaves differently, look for per-function resources.
- EC2 `LaunchTime` and unbroken CloudWatch datapoints are cheap, reliable proof of whether an
  instance actually cycled.
- Async Lambda failures retry twice, so **errors arrive in multiples of three**. A count of 3
  is one real failure, not three.
- RDS restarts a stopped instance automatically after 7 days — a scheduler that stops it
  nightly is not a substitute for that being true.
