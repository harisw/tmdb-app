terraform {
  required_version = ">=1.10"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }

  backend "s3" {
    bucket       = "chill-tfstate-012504444753"
    key          = "scheduling/terraform.tfstate"
    region       = "ap-northeast-2"
    encrypt      = true
    use_lockfile = true
  }
}

provider "aws" {
  region = "ap-northeast-2"
}

data "aws_instance" "chill" {
  instance_id = var.ec2_instance_id
}

data "aws_db_instance" "chill" {
  db_instance_identifier = var.rds_instance_identifier
}

