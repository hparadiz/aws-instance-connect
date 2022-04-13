# AWS Instance Connect PHP
[![Latest Unstable Version](https://poser.pugx.org/hparadiz/aws-instance-connect/v/stable)](https://packagist.org/packages/hparadiz/aws-instance-connect)
[![License](https://poser.pugx.org/hparadiz/aws-instance-connect/license)](https://packagist.org/packages/hparadiz/aws-instance-connect)

This tool lets you SSH into AWS EC2 instances with nothing but your AWS IAM credentials that you probably already have in your home directory if you work with AWS.

To be more specific it uses the AWS SDK to access AWS Instance Connect to SSH into your EC2 instances quickly with a high degree of security because a key is generated for one time use and then immediately destroyed.

[![asciicast](https://asciinema.org/a/FMxcjIYuKauXPm4kVFR02gQqt.svg)](https://asciinema.org/a/FMxcjIYuKauXPm4kVFR02gQqt)
## Installation
`composer global require hparadiz/aws-instance-connect`

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

// for the key to be authorized but for no SSH connection to be created
// to use the key with something else like SCP or a tunnel
$IC->noConnect = false;

$IC->start();
```

## FAQ
### Why?

By using AWS credentials to login instead of SSH keys it is easier to manage your users and you can actually withdraw access without having to manually delete any keys. You can add or remove users simply by adding or removing them from your AWS console through the normal user management interface.

### Okay but seriously. Is this secure?
The code is super simple. Only about 200 lines of code. Feel free to read it. I make use of phpseclib to make the keys and the official AWS SDK does the actual leg work.

### Why PHP?
Since I work with PHP projects this is just conveniant for me.

# Support
I wrote this tool for myself but I hope others find it useful.
I'm happy to work on this further if people begin to use it.
Feel free to make feature requests. I'm eager to hear about other use cases.

If you wish to support this project please see the links below.

Ko-Fi: https://ko-fi.com/henryparadiz

BTC - bc1qqqejxpuxgeyxx5fkyan8tpeuwyenks8fa4zldf