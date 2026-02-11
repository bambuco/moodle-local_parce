<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Spanish language strings for local_parce
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['aria_close_chat'] = 'Cerrar chat';
$string['aria_loading'] = 'Cargando';
$string['aria_message_input'] = 'Campo de mensaje';
$string['chat_bubble_label'] = 'Abrir chat';
$string['chat_error'] = 'Ocurrió un error. Por favor intenta más tarde.';
$string['chat_error_processing'] = 'Lo siento, no pude procesar tu pregunta. Por favor intenta de nuevo.';
$string['chat_loading'] = 'Procesando tu pregunta...';
$string['chat_send'] = 'Enviar';
$string['chat_welcome'] = '¡Hola! ¿Cómo podemos ayudarte hoy?';

$string['default_answer_question_prompt'] = 'Responde a la pregunta del usuario de manera precisa, clara y concisa. Usando solo el texto de "content" y considerando el historial de "previous". Proporciona solo información adecuada para un contexto educativo.';
$string['default_question_plan_prompt'] = 'Basado en el JSON del usuario, responde con un JSON válido que contenga:\n"type": debe ser uno de: greeting, content, dates\n"params": Para el tipo "content" las palabras clave que definen el contenido a buscar, para fechas los rangos de fechas, para "greeting" un saludo aleatorio+respetuoso+cordial\nResponde solo el JSON puro, sin bloques de código, sin markdown y sin texto adicional.';
$string['defaulttitle'] = 'Parce - Chat de Preguntas y Respuestas';
$string['error_ai_failed'] = 'No se pudo generar una respuesta';
$string['error_ai_unavailable'] = 'El servicio de IA no está disponible en este momento.';
$string['error_empty_question'] = 'Por favor ingresa una pregunta.';
$string['error_no_content'] = 'No se generó contenido de respuesta.';
$string['error_processing_question'] = 'Ocurrió un error al procesar tu pregunta. Por favor intenta de nuevo.';
$string['placeholder'] = 'Escribe tu pregunta...';
$string['pluginname'] = 'Parce - Widget de Chat Q&A';
$string['setting_ai_instructions_heading'] = 'Instrucciones para Acciones de IA';
$string['setting_ai_instructions_heading_desc'] = 'Configura las instrucciones del sistema para cada acción de IA. Estas instrucciones guían el comportamiento del modelo de IA para tipos específicos de preguntas.';
$string['setting_answer_question_prompt'] = 'Instrucción para Responder Preguntas';
$string['setting_answer_question_prompt_desc'] = 'La instrucción del sistema que guía a la IA al responder directamente las preguntas de los usuarios. Esta instrucción se envía al modelo de IA con cada solicitud de respuesta a preguntas.';
$string['setting_chat_title'] = 'Título de la Ventana de Chat';
$string['setting_chat_title_desc'] = 'El título que se muestra en el encabezado de la ventana de chat';
$string['setting_enable_guests'] = 'Habilitar para Usuarios Invitados';
$string['setting_enable_guests_desc'] = 'Permitir que los usuarios invitados accedan al widget de chat';
$string['setting_enabled'] = 'Habilitar Widget de Chat Parce';
$string['setting_enabled_desc'] = 'Cuando está habilitado, el widget de chat flotante aparecerá en todas las páginas para usuarios registrados';
$string['setting_question_plan_prompt'] = 'Instrucción para entender las Preguntas';
$string['setting_question_plan_prompt_desc'] = 'La instrucción del sistema que guía a la IA al crear un plan o esquema estructurado para una pregunta o tema. Esta instrucción se envía al modelo de IA con cada solicitud de plan de preguntas.';
