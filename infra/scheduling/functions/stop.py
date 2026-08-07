import boto3, os

ec2 = boto3.client('ec2', region_name='ap-northeast-2')
rds = boto3.client('rds', region_name='ap-northeast-2')

#Each resource is handled independently so a failure on one no longer
#skips the other. Errors are collected and re-raised at the end so the
#Errors metric still fires, but only after both calls have been tried.
def lambda_handler(event, context):
    errors = []

    try:
        ec2.stop_instances(InstanceIds=[os.environ['EC2_INSTANCE_ID']])
        print("EC2 stop requested")
    except Exception as e:
        errors.append(f"EC2: {e}")
        print(f"[ERROR] EC2 stop failed: {e}")

    try:
        db = os.environ['RDS_INSTANCE_ID']
        status = rds.describe_db_instances(
            DBInstanceIdentifier=db
        )['DBInstances'][0]['DBInstanceStatus']

        #StopDBInstance only accepts 'available'; anything else raises
        #InvalidDBInstanceState and burns the two async retries for nothing.
        if status == 'available':
            rds.stop_db_instance(DBInstanceIdentifier=db)
            print("RDS stop requested")
        else:
            print(f"RDS stop skipped, status is {status}")
    except Exception as e:
        errors.append(f"RDS: {e}")
        print(f"[ERROR] RDS stop failed: {e}")

    if errors:
        raise RuntimeError("; ".join(errors))
