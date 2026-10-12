import boto3, os

ec2 = boto3.client('ec2', region_name='ap-northeast-2')
rds = boto3.client('rds', region_name='ap-northeast-2')

def lambda_handler(event, context):
    errors = []

    try:
        ec2.start_instances(InstanceIds=[os.environ['EC2_INSTANCE_ID']])
        print("EC2 start requested")
    except Exception as e:
        errors.append(f"EC2: {e}")
        print(f"[ERROR] EC2 start failed: {e}")

    try:
        db = os.environ['RDS_INSTANCE_ID']
        status = rds.describe_db_instances(
            DBInstanceIdentifier=db
        )['DBInstances'][0]['DBInstanceStatus']

        #StartDBInstance only accepts 'stopped'; when the nightly stop did not
        #land the instance is still 'available' and this used to fail daily.
        if status == 'stopped':
            rds.start_db_instance(DBInstanceIdentifier=db)
            print("RDS start requested")
        else:
            print(f"RDS start skipped, status is {status}")
    except Exception as e:
        errors.append(f"RDS: {e}")
        print(f"[ERROR] RDS start failed: {e}")

    if errors:
        raise RuntimeError("; ".join(errors))
