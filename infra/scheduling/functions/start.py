import boto3, os

ec2 = boto3.client('ec2', region_name='ap-northeast-2')
rds = boto3.client('rds', region_name='ap-northeast-2')

def lambda_handler(ev, ctx):
    ec2.start_instances(InstanceIds=[os.environ['EC2_INSTANCE_ID']])
    rds.start_db_instance(DBInstanceIdentifier=os.environ['RDS_INSTANCE_ID'])
    print("Started EC2 and RDS")