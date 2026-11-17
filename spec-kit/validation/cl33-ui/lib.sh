#!/bin/bash
# Driver HTTP: acessa o Moodle real (Apache local) como um navegador, por cookies.
BASE=http://localhost:8080
PW="${CL33_PASSWORD:?defina CL33_PASSWORD}"
cu() { curl -s -L --connect-to localhost:8080:127.0.0.1:80 "$@"; }
login() { # user jar
  local u=$1 j=$2
  rm -f $j
  local tok=$(cu -c $j -b $j $BASE/login/index.php | grep -o 'name="logintoken" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  cu -c $j -b $j -o /dev/null -w "%{http_code} %{url_effective}\n" --data-urlencode "username=$u" --data-urlencode "password=$PW" --data-urlencode "logintoken=$tok" $BASE/login/index.php
}
sesskey() { # jar url
  cu -b $1 -c $1 "$2" | grep -o '"sesskey":"[^"]*"' | head -1 | sed 's/.*:"//;s/"//'
}
