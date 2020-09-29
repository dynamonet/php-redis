@echo off 
docker run -it -v %CD%:/usr/src/app -w /usr/src/app --network="host" --name="producer" dynamonet/php73 /bin/bash
pause
