@echo off 
docker run -it -v %CD%:/usr/src/app -w /usr/src/app --network="host" dynamonet/php73 /bin/bash
pause
