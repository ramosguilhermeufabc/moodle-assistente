#!/bin/bash
# snap.sh BASEEV -> fila do plugin no curso 6 desde BASEEV, e as sínteses (versão, análise, contexto) do curso
M="mysql -h db -umoodle -ppassword moodle"
echo "--- fila (curso 6)"
$M -e "select id,event_type,status,left(replace(payload,'\"',''),70) payload,left(coalesce(response,''),70) resposta from mdl_local_assistente_event where course_id=6 and id>$1 order by id" 2>&1 | sed 's/,identity.*//'
echo "--- sinteses"
$M -e "select s.id,s.section_id,s.version,left(s.input_fingerprint,12) fp,left(s.content,30) conteudo,s.analysis_id,a.processing_id,(select count(*) from mdl_local_assistente_processing_context c where c.processing_id=a.processing_id) ctx from mdl_local_assistente_pedagogical_synthesis s join mdl_local_assistente_analysis a on a.id=s.analysis_id where s.course_id=6 order by s.section_id,s.version" 2>&1
