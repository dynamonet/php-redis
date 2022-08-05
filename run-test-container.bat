@echo off 
docker run -it -v %CD%:/usr/src/app -w /usr/src/app --network="host" dynamonet/php:7.3 /bin/bash
pause
