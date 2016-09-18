# Let's Encrypt client in PHP
le-client is a Let's Encrypt service client

## Usage

> composer dumpautoload

### First time
1. Make account's key
> php bin/le.php make_key data/private.key

2. Register account
> php bin/le.php reg_account data/private.key

3. Make challenge
> php bin/le.php make_challenge data/private.key example.com

4. Put domain confirmation details to web server

5. Challnge domain confirmation
> php bin/le.php challenge data/private.key uri token file location

6. Make domain's key
> php bin/le.php make_key data/domain.key

7. Make cert
> php bin/le.php make_cert data/domain.key example.com data/private.key
