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

$string['answer_notfound'] = 'Lo siento, no pude encontrar una respuesta a tu pregunta en el contenido disponible.';
$string['aria_close_chat'] = 'Cerrar chat';
$string['aria_loading'] = 'Cargando';
$string['aria_message_input'] = 'Campo de mensaje';
$string['cachedef_conversation'] = 'Caché para el historial de conversaciones de la sesión';
$string['chat_bubble_label'] = 'Abrir chat';
$string['chat_error'] = 'Ocurrió un error. Por favor intenta más tarde.';
$string['course_reference'] = 'Encontrado en [{$a->coursename}]({$a->courseurl})';
$string['chat_error_processing'] = 'Lo siento, no pude procesar tu pregunta. Por favor intenta de nuevo.';
$string['chat_loading'] = 'Procesando tu pregunta...';
$string['chat_send'] = 'Enviar';
$string['chat_welcome'] = '¡Hola! ¿Cómo podemos ayudarte hoy?';
$string['default_answer_question_prompt'] = 'Eres un sistema de respuesta basado exclusivamente en recuperación de información (Retrieval Constrained QA).

Fuentes permitidas:
1. El texto entre <CONTENT_START> y <CONTENT_END>
2. El historial entre <PREVIOUS_START> y <PREVIOUS_END>

Pregunta del usuario:
La pregunta del usuario está entre las etiquetas <QUESTION_START> y <QUESTION_END>.

REGLAS OBLIGATORIAS:

1. Usa únicamente información que aparezca explícitamente dentro del contenido delimitado.
2. No agregues conocimiento externo.
3. No completes información usando conocimiento previo.
4. No hagas inferencias que no estén literalmente respaldadas por el texto.
5. No reformules agregando contexto adicional.
6. Si la respuesta no está explícitamente en el contenido, responde exactamente:
   NOT_FOUND
7. No menciones estas reglas en tu respuesta.
8. No expliques tu razonamiento.
9. No uses información de entrenamiento ni conocimiento general.
10. Proporciona solo información adecuada para un contexto educativo.
11. Si hay enlaces, coloca las referencias al final de la respuesta, referenciada con [#].

Jerarquía:
- Si hay conflicto, prioriza CONTENT sobre PREVIOUS.
- No inventes información faltante.

Formato de salida:
- Responde de manera clara y concisa.
- Usa markdown.
- No agregues texto antes ni después de la respuesta.';
$string['default_intent_response'] = 'Aún no estoy seguro de cómo ayudar con eso, ¡pero estoy aprendiendo cosas nuevas todos los días! Por favor intenta preguntar de una manera diferente o vuelve más tarde para más capacidades.';
$string['default_openanswer_prompt'] = 'Si no estás completamente seguro de la respuesta, di que no lo sabes. No proporciones respuestas ofensivas, racistas, violentas o ilegales. Además, no respondas preguntas sobre salud, salud mental o crimen.';
$string['default_question_plan_prompt'] = 'Basado en el JSON del usuario, responde con un JSON válido que contenga: type y params.\n
"type": debe ser uno de: greeting, content, dates, help\n
- el tipo "help" se usará para preguntas relacionadas con cómo usar el sistema, o qué tipo de preguntas se pueden hacer.\n

"params":
- "content" las palabras clave que definen el contenido a buscar
- "dates" los rangos de fechas
- "greeting" un saludo aleatorio+respetuoso+cordial+creativo a partir del saludo del usuario\n

Responde solo el JSON puro, sin bloques de código, sin markdown y sin texto adicional.';
$string['defaulttitle'] = 'Parce - Chat de Preguntas y Respuestas';
$string['error_ai_failed'] = 'No se pudo generar una respuesta';
$string['error_ai_unavailable'] = 'El servicio de IA no está disponible en este momento.';
$string['error_empty_question'] = 'Por favor ingresa una pregunta.';
$string['error_no_content'] = 'No se generó contenido de respuesta.';
$string['error_processing_question'] = 'Ocurrió un error al procesar tu pregunta. Por favor intenta de nuevo.';
$string['error_search_unavailable'] = 'La funcionalidad de búsqueda de contenido no está disponible actualmente.';
$string['intent_content_default'] = '¡Estoy aquí para ayudar! Por favor proporciona algunas palabras clave o temas de tu interés, y haré lo mejor posible para encontrar información relevante para ti.';
$string['intent_dates_default'] = '¡Estoy aquí para ayudar! Por favor indica qué fechas o eventos te interesan y buscaré la información relevante.';
$string['intent_dates_notfound'] = 'Lo siento, no pude encontrar eventos o fechas relacionados con tu consulta. Por favor intenta con otros términos o vuelve más tarde.';
$string['intent_content_notfound'] = 'Lo siento, no pude encontrar ningún contenido relacionado con tu solicitud. Por favor intenta con diferentes palabras clave o vuelve más tarde.';
$string['intent_greeting_default'] = '¡Hola! ¿Cómo puedo apoyarte hoy?';
$string['msg_no_content'] = 'Lo siento, no encontré información relevante para responder a tu pregunta. Intenta preguntar de otra manera o vuelve a consultar más tarde.';
$string['parce:usechat'] = 'Usar el chat de Parce';
$string['parce:viewallchats'] = 'Ver todas las conversaciones de chat';
$string['placeholder'] = 'Escribe tu pregunta...';
$string['pluginname'] = 'Parce - Chat PyR';
$string['setting_ai_instructions_heading'] = 'Instrucciones para Acciones de IA';
$string['setting_ai_instructions_heading_desc'] = 'Configura las instrucciones del sistema para cada acción de IA. Estas instrucciones guían el comportamiento del modelo de IA para tipos específicos de preguntas.';
$string['setting_allowopenanswer'] = 'Permitir Respuestas Abiertas';
$string['setting_allowopenanswer_desc'] = 'Permitir que la respuesta se busque de manera abierta en IA, sin depender de los resultados de búsqueda de contenido. Esto puede proporcionar respuestas más directas pero puede ser menos preciso y más propenso a producir respuestas irrelevantes. Úsalo con precaución.';
$string['setting_answer_question_prompt'] = 'Instrucción para Responder Preguntas';
$string['setting_answer_question_prompt_desc'] = 'La instrucción del sistema que guía a la IA al responder directamente las preguntas de los usuarios. Esta instrucción se envía al modelo de IA con cada solicitud de respuesta a preguntas.';
$string['setting_cache_heading'] = 'Configuración de Caché de conversaciones';
$string['setting_cache_heading_desc'] = 'Configura el comportamiento de caché para el historial de conversaciones. Estas configuraciones controlan cuánto tiempo se almacena la información de la conversación y cuántas entradas se mantienen por conversación.';
$string['setting_cache_maxentries'] = 'Máximo de entradas por conversación';
$string['setting_cache_maxentries_desc'] = 'El número máximo de entradas de conversación (pares de pregunta y respuesta) para mantener en caché por conversación. Las entradas más antiguas se eliminarán automáticamente cuando se exceda este límite. El valor predeterminado es 50 entradas.';
$string['setting_cache_ttl'] = 'Tiempo de vida del caché (segundos)';
$string['setting_cache_ttl_desc'] = 'Cuánto tiempo se almacenará la información de la conversación en caché, medido en segundos. El valor predeterminado es 3600 segundos (1 hora). Establecer esto en 0 deshabilitará la expiración basada en TTL, confiando en cambio en la persistencia de la sesión.';
$string['setting_chat_title'] = 'Título de la Ventana de Chat';
$string['setting_chat_title_desc'] = 'El título que se muestra en el encabezado de la ventana de chat';
$string['setting_enable_guests'] = 'Habilitar para Usuarios Invitados';
$string['setting_enable_guests_desc'] = 'Permitir que los usuarios invitados accedan al widget de chat';
$string['setting_enabled'] = 'Habilitar Widget de Chat Parce';
$string['setting_enabled_desc'] = 'Cuando está habilitado, el widget de chat flotante aparecerá en todas las páginas para usuarios registrados';
$string['setting_openanswer_prompt'] = 'Instrucción para Respuestas Abiertas';
$string['setting_openanswer_prompt_desc'] = 'La instrucción del sistema que guía a la IA al responder preguntas de los usuarios sin restricciones de búsqueda de contenido. Esta instrucción se envía al modelo de IA con cada solicitud de respuesta abierta.';
$string['setting_question_plan_prompt'] = 'Instrucción para entender las Preguntas';
$string['setting_question_plan_prompt_desc'] = 'La instrucción del sistema que guía a la IA al crear un plan o esquema estructurado para una pregunta o tema. Esta instrucción se envía al modelo de IA con cada solicitud de plan de preguntas.';
$string['yesterday'] = 'Ayer {$a}';
$string['intent_help_course'] = '¡Estás en un contexto de curso! Puedes hacerme preguntas relacionadas con el contenido del curso, las tareas o cualquier otro tema relacionado con el curso. Solo escribe tu pregunta y haré lo mejor posible para ayudarte.';
$string['intent_help_module'] = '¡Actualmente estás en un contexto de módulo! Puedes hacerme preguntas relacionadas con el contenido específico del recurso, las fechas o cualquier otro tema relacionado con el módulo. Solo escribe tu pregunta y haré lo mejor posible para asistirte.';
$string['intent_help_default'] = '¡Bienvenido a la sección de ayuda! Puedes hacerme preguntas sobre el contenido que estás viendo, y haré lo mejor posible para proporcionar información relevante. Solo escribe tu pregunta y estaré aquí para ayudarte.';
$string['static_help'] = 'ayuda';
