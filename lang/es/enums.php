<?php

return [

    'campaign_status' => [
        'draft' => 'Borrador',
        'active' => 'Activa',
        'paused' => 'Pausada',
        'completed' => 'Completada',
        'archived' => 'Archivada',
    ],

    'user_role' => [
        'super_admin' => 'Super Administrador',
        'admin_campaign' => 'Administrador de Campaña',
        'coordinator' => 'Coordinador',
        'leader' => 'Líder',
        'reviewer' => 'Revisor',
    ],

    'voter_status' => [
        'pending_review' => 'Pendiente de Revisión',
        'rejected_census' => 'Rechazado por Censo',
        'verified_census' => 'Verificado por Censo',
        'correction_required' => 'Corrección Requerida',
        'verified_call' => 'Verificado por Llamada',
        'confirmed' => 'Confirmado',
        'voted' => 'Votó',
        'did_not_vote' => 'No Votó',
    ],

    'call_result' => [
        'answered' => 'Contestó',
        'confirmed' => 'Confirmado',
        'no_answer' => 'No Contestó',
        'busy' => 'Ocupado',
        'wrong_number' => 'Número Equivocado',
        'invalid_number' => 'Número Inválido',
        'rejected' => 'Rechazado',
        'callback_requested' => 'Llamar de Nuevo',
        'not_interested' => 'No Interesado',
    ],

    'question_type' => [
        'yes_no' => 'Sí/No',
        'scale' => 'Escala',
        'text' => 'Texto Libre',
        'multiple_choice' => 'Selección Múltiple',
        'single_choice' => 'Selección Única',
    ],

    'message_type' => [
        'birthday' => 'Cumpleaños',
        'reminder' => 'Recordatorio',
        'campaign' => 'Campaña',
        'custom' => 'Personalizado',
    ],

    'message_channel' => [
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'email' => 'Email',
    ],

    'message_status' => [
        'pending' => 'Pendiente',
        'scheduled' => 'Programado',
        'sent' => 'Enviado',
        'delivered' => 'Entregado',
        'read' => 'Leído',
        'failed' => 'Fallido',
        'clicked' => 'Clic',
    ],

    'batch_status' => [
        'pending' => 'Pendiente',
        'processing' => 'Procesando',
        'completed' => 'Completado',
        'failed' => 'Fallido',
    ],

    'priority' => [
        'low' => 'Baja',
        'medium' => 'Media',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ],

    'assignment_status' => [
        'pending' => 'Pendiente',
        'in_progress' => 'En Progreso',
        'completed' => 'Completado',
        'reassigned' => 'Reasignado',
    ],

];
