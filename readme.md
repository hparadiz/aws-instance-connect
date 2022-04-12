# AWS Instance Connect PHP
This tool will let you use AWS Instance Connect to SSH into your EC2 instances quickly with a high degree of security using your AWS credentials and a generated public/private key created and then immediately destroyed.

## Configuration
Please follow the AWS instructions for setting up your AWS credentials in `~/.aws/credentials`

The default region will be pulled from `~/.aws/config`

## Setup
1. Go to IAM -> Policies in your AWS console.
2. Create a new JSON policy.
3. Paste this in and save.
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": "ec2-instance-connect:SendSSHPublicKey",
            "Resource": "arn:aws:ec2:region:account-id:instance/*"
        },
        {
            "Effect": "Allow",
            "Action": "ec2:DescribeInstances",
            "Resource": "*"
        }
    ]
}
```

This policy will allow AWS Instance Connect to work with all your EC2 instances in all regions. Please refer to AWS documentation for more targeted security policy rules.

## Usage via CLI
```
$ ic --help
Usage: ic [options] [<instanceId>] [operands]

Operands:
  [<instanceId>]  Target instance

Options:
  -v, --version       Show version information and quit
  -h, --help          Show this help and quit
  -N, --no-connect    Authorize the SSH key and exit.
  -u, --user <arg>    Set user for SSH connection. Defaults to ubuntu.
  -R, --region <arg>  AWS Region
  -n, --name <arg>    The name of the instance (AWS Tag 'Name')
```

## Usage with code
```php
$IC = new InstanceConnect();
$IC->region = 'us-east-1';

// the username for the SHH connection
$IC->user = 'ubuntu';

// optional (will prompt for an instance if not set)
$IC->name = 'i-0e19ee2d63877633f';

$IC->publicKey = '/home/user/.ssh/rsa.pub';
$IC->privateKey = '/home/user/.ssh/rsa';

// if you wish for the key to be authorized but for no SSH connection to be created (incase you wish to use the key with something else like SCP)
$IC->noConnect = false;

$IC->start();
```

## Why?

The security benefit of using this tool lets you create EC2 instances with no authorized SSH keys at all initially. The AWS login is instead used. This lets you add or remove users simply by adding or removing them from your AWS console through the normal user management flow.

# Support
Buy me a beer!

BTC - bc1qqqejxpuxgeyxx5fkyan8tpeuwyenks8fa4zldf


