import boto3, os

ec2 = boto3.client('ec2', region_name='ap-northeast-2')
rds = boto3.client('rds', region_name='ap-northeast-2')

def lambda_handler(event, context):
    ec2.stop_instances(InstanceIds=[os.environ['EC2_INSTANCE_ID']])
    rds.stop_db_instance(DBInstanceIdentifier=os.environ['RDS_INSTANCE_ID'])
    print("Stopped EC2 and RDS")