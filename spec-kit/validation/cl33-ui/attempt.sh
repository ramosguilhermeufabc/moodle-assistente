#!/bin/bash
# attempt.sh user jar tf num essay -> faz uma tentativa completa pela interface
. ./lib.sh
u=$1; j=$2; tf=$3; num=$4; essay=$5
login $u $j > /dev/null
sk=$(sesskey $j $BASE/mod/quiz/view.php?id=1)
cu -b $j -c $j -o a.html -w "%{url_effective}\n" --data "cmid=1&sesskey=$sk" $BASE/mod/quiz/startattempt.php
att=$(grep -o 'name="attempt" value="[0-9]*"' a.html | head -1 | grep -o '[0-9]*')
uid=$att
seq1=$(grep -o 'name="q'$uid':1_:sequencecheck" value="[0-9]*"' a.html | grep -o 'value="[0-9]*"' | grep -o '[0-9]*')
cu -b $j -c $j -o /dev/null --data-urlencode "q$uid:1_:sequencecheck=$seq1" --data-urlencode "q$uid:1_answer=$tf" --data-urlencode "q$uid:2_:sequencecheck=$seq1" --data-urlencode "q$uid:2_answer=$num" --data-urlencode "q$uid:3_:sequencecheck=$seq1" --data-urlencode "q$uid:3_answer=$essay" --data-urlencode "q$uid:3_answerformat=1" --data "attempt=$att&thispage=0&nextpage=-1&timeup=0&sesskey=$sk&slots=1,2,3" "$BASE/mod/quiz/processattempt.php?cmid=1"
cu -b $j -c $j -o /dev/null -w "finish %{url_effective}\n" --data "attempt=$att&finishattempt=1&timeup=0&slots=&cmid=1&sesskey=$sk" $BASE/mod/quiz/processattempt.php
echo "attempt id=$att"
