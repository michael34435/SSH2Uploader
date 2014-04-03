SSH2Uploader
============

PHP/phpseclib based uploader tool, support multiple hosts

---

##config

SSH2Uploader will load config file in the base folder, i.e: config.json(default), config1.json ... etc

include these options below.

**user**:Login as this username.

**password**:Password to login ssh2 service.

**hosts(array)**:Upload server list.

**private_key**:*.ppk file.

**port**:SSH2 server port.

**destination**:Remote folder to upload.

**package**:Local folder to upload

##ENV params

SSH2Uploader accepts like:

***CONFIG***:Assign config file to load, split by ",".

***SU***:Assign permission as 777, split by ",".

***RESET***:This option will delete assigned folder first, then create it as a new one.(value=0,1)

