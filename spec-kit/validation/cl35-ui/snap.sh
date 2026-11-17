#!/bin/bash
# snap.sh BASELOG BASEEV -> eventos do mod_forum no registro do Moodle, fila do plugin e posts/discussões desde o ponto de partida
b=$1; e=$2
mysql -h db -umoodle -ppassword moodle -e "select id,eventname,userid,objectid,left(other,70) other from mdl_logstore_standard_log where id>$b and component='mod_forum' and eventname not like '%viewed%' and eventname not like '%subscription%' order by id; select e.id,e.event_type,e.status,e.payload from mdl_local_assistente_event e where e.id>$e order by e.id" 2>&1 | sed 's/,\"identity.*//' | cut -c1-200
