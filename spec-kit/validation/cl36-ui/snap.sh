#!/bin/bash
# snap.sh BASELOG BASEEV BASEAN [CURSO] -> eventos de participacao no registro do Moodle, fila do plugin e frequencia gravada desde o ponto de partida
b=$1; e=$2; a=$3; c=${4:-5}
M="mysql -h db -umoodle -ppassword moodle"
echo "--- eventos Moodle"
$M -e "select id,eventname,userid,relateduserid,objectid,contextinstanceid cmid from mdl_logstore_standard_log where id>$b and courseid=$c and (eventname like '%assessable_submitted' or eventname like '%completion_updated' or eventname like '%user_graded' or eventname like '%grade_deleted' or (component='mod_forum' and (eventname like '%post_%' or eventname like '%discussion_%') and eventname not like '%viewed%')) order by id" 2>&1
echo "--- fila do plugin"
$M -e "select id,event_type,status,left(replace(payload,'\"',''),90) payload from mdl_local_assistente_event where id>$e and course_id=$c order by id" 2>&1 | sed 's/,identity.*//'
echo "--- frequencia gravada (analise, estudante, secao, participando/total, %)"
$M -e "select an.id analise,f.student_id est,f.section_id secao,f.participating_activities part,f.total_activities total,f.percentage pct,(select group_concat(concat(fa.activity_id,':',fa.participated) order by fa.activity_id) from mdl_local_assistente_frequency_activity fa where fa.frequency_id=f.id) ativ from mdl_local_assistente_analysis an join mdl_local_assistente_frequency f on f.analysis_id=an.id where an.id>$a and an.course_id=$c and an.analysis_type='frequency' order by an.id,f.student_id,f.section_id" 2>&1
