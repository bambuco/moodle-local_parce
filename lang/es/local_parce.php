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
$string['chat_empty'] = 'Todavía no hay mensajes en esta conversación.';
$string['chat_error'] = 'Ocurrió un error. Por favor intenta más tarde.';
$string['chat_error_processing'] = 'Lo siento, no pude procesar tu pregunta. Por favor intenta de nuevo.';
$string['chat_history_error'] = 'No se pudo cargar la conversación activa.';
$string['chat_loading'] = 'Procesando tu pregunta...';
$string['chat_retry'] = 'Intentar de nuevo';
$string['chat_send'] = 'Enviar';
$string['chat_welcome'] = '¡Hola! ¿Cómo podemos ayudarte hoy?';
$string['content_suggestions'] = 'No pude extraer una respuesta directa, pero encontré estos recursos relacionados:';
$string['conversation_started'] = 'Se inició una nueva conversación porque la anterior llegó a su límite.';
$string['conversation_usage'] = 'Uso de la conversación';
$string['conversation_usage_aria'] = 'Límite estimado de la conversación consumido';
$string['course_reference'] = 'Encontrado en [{$a->coursename}]({$a->courseurl})';
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
$string['default_question_plan_prompt'] = 'Responde con JSON válido que contenga "type" y "params".

"type" debe ser uno de: greeting, content, resource, dates, grades, progress, help.
- "resource": solicitudes explícitas para buscar, mostrar, encontrar, ubicar o acceder a cursos, actividades o recursos de Moodle. Esta intención devuelve enlaces directamente y no responde preguntas sobre el contenido.
- "content": preguntas que requieren explicar o responder usando el contenido encontrado.
- "dates": consultas sobre eventos o rangos de fechas.
- "grades": preguntas sobre las calificaciones, puntajes o retroalimentación de evaluación del usuario actual. No lo uses para solicitar calificaciones de otro usuario.
- "progress": preguntas sobre el progreso del usuario actual en sus cursos o sobre actividades completadas, pendientes, aprobadas o reprobadas. No lo uses para solicitar el progreso de otro usuario.
- "greeting": saludos.
- "help": preguntas sobre cómo usar el sistema o qué se puede preguntar.

"params":
- Para "resource", "resourcetype" es obligatorio. Los tipos de módulos usados en el curso aparecen como un objeto JSON entre RESOURCE_TYPES_START y RESOURCE_TYPES_END: cada clave es el nombre corto permitido y su booleano indica si el componente declara que puede generar calificaciones. Usa ["core_course"] para cursos, ["*"] para todos los tipos de módulos disponibles o un arreglo de claves concretas del objeto para los tipos solicitados. Usa "content" con solo los términos de la pregunta actual que distinguen el nombre del recurso dentro del contexto; usa un arreglo vacío si no existen. Usa el historial únicamente para resolver referencias explícitas de la pregunta actual.
- Para "content", usa "content" con el tema y los términos que distinguen la respuesta solicitada, pero omite expresiones sintácticas genéricas. Conserva conceptos como ventajas, causas o definición. Por ejemplo, "cosas buenas de las redes sociales" debe buscar "ventajas redes sociales".
- Para "dates", usa "dates" con los rangos o términos de fecha.
- Para "grades", usa "grades" solo con el nombre distintivo del curso o elemento de calificación, cuando esté presente. Usa un arreglo vacío para una consulta general sobre todas las calificaciones.
- Para "progress", usa "progress" solo con el nombre distintivo del curso o actividad, cuando esté presente. Opcionalmente usa "status" con uno de estos valores: incomplete, completed, passed, failed. Usa "status": "incomplete" para actividades pendientes.
- Para "greeting", incluye un saludo respetuoso y cordial.

Responde solo el JSON puro, sin bloques de código, Markdown ni texto adicional.';
$string['defaulttitle'] = 'Parce - Asistente del sitio';
$string['error_ai_failed'] = 'No se pudo generar una respuesta';
$string['error_ai_unavailable'] = 'El servicio de IA no está disponible en este momento.';
$string['error_empty_question'] = 'Por favor ingresa una pregunta.';
$string['error_guest_history'] = 'Los usuarios invitados no pueden acceder al historial de conversaciones.';
$string['error_no_content'] = 'No se generó contenido de respuesta.';
$string['error_processing_question'] = 'Ocurrió un error al procesar tu pregunta. Por favor intenta de nuevo.';
$string['error_question_too_long'] = 'La pregunta es demasiado larga. La longitud máxima es de 4000 caracteres.';
$string['error_rate_limited'] = 'Se han realizado demasiadas solicitudes de IA. Intenta de nuevo más tarde.';
$string['error_search_unavailable'] = 'La funcionalidad de búsqueda de contenido no está disponible actualmente.';
$string['eventconversationhistoryviewed'] = 'Historial de conversación consultado';
$string['historyadminlink'] = 'Historial de conversaciones de Parce';
$string['historyback'] = 'Volver al nivel anterior del historial';
$string['historybreadcrumb'] = 'Navegación del historial de conversaciones';
$string['historycompletiontokens'] = 'Tokens de respuesta';
$string['historycontexts'] = 'Contextos con historial';
$string['historyconversation'] = 'Turnos de la conversación';
$string['historyconversationlabel'] = 'Conversación';
$string['historyconversations'] = 'Conversaciones';
$string['historyempty'] = 'No se encontraron conversaciones históricas.';
$string['historyend'] = 'Fin de los resultados.';
$string['historyerror'] = 'No se pudo cargar el historial. Inténtalo de nuevo.';
$string['historyguestsession'] = 'Sesión de invitado';
$string['historyloading'] = 'Cargando historial…';
$string['historyloadmore'] = 'Cargar más';
$string['historyprompttokens'] = 'Tokens de entrada';
$string['historyresultslimited'] = 'Se muestran solamente los {$a} resultados más recientes. Puede haber más.';
$string['historysearch'] = 'Buscar en el historial de conversaciones';
$string['historysearchnoresults'] = 'No hay conversaciones que coincidan con esta frase.';
$string['historysearchplaceholder'] = 'Buscar conversaciones';
$string['historyselectconversation'] = 'Selecciona una conversación para verla.';
$string['historytitle'] = 'Mi historial de conversaciones de Parce';
$string['historytokens'] = 'Consumo de tokens';
$string['historyturns'] = 'Turnos';
$string['historyunavailablecontext'] = '[Contexto no disponible]';
$string['historyunavailableuser'] = 'Usuario no disponible';
$string['intent_content_default'] = '¡Estoy aquí para ayudar! Por favor proporciona algunas palabras clave o temas de tu interés, y haré lo mejor posible para encontrar información relevante para ti.';
$string['intent_content_notfound'] = 'Lo siento, no pude encontrar ningún contenido relacionado con tu solicitud. Por favor intenta con diferentes palabras clave o vuelve más tarde.';
$string['intent_dates_default'] = '¡Estoy aquí para ayudar! Por favor indica qué fechas o eventos te interesan y buscaré la información relevante.';
$string['intent_dates_notfound'] = 'Lo siento, no pude encontrar eventos o fechas relacionados con tu consulta. Por favor intenta con otros términos o vuelve más tarde.';
$string['intent_grades_notfound'] = 'No encontré calificaciones visibles relacionadas con tu pregunta.';
$string['intent_greeting_default'] = '¡Hola! ¿Cómo puedo apoyarte hoy?';
$string['intent_help_course'] = '¡Estás en un contexto de curso! Puedes hacerme preguntas relacionadas con el contenido del curso, las tareas o cualquier otro tema relacionado con el curso. Solo escribe tu pregunta y haré lo mejor posible para ayudarte.';
$string['intent_help_default'] = '¡Bienvenido a la sección de ayuda! Puedes hacerme preguntas sobre el contenido que estás viendo, y haré lo mejor posible para proporcionar información relevante. Solo escribe tu pregunta y estaré aquí para ayudarte.';
$string['intent_help_module'] = '¡Actualmente estás en un contexto de módulo! Puedes hacerme preguntas relacionadas con el contenido específico del recurso, las fechas o cualquier otro tema relacionado con el módulo. Solo escribe tu pregunta y haré lo mejor posible para asistirte.';
$string['intent_progress_notfound'] = 'No encontré información visible de progreso relacionada con tu pregunta.';
$string['intent_resource_notfound'] = 'Lo siento, no encontré recursos relacionados con tu búsqueda. Intenta usar el nombre o palabras distintivas del recurso.';
$string['msg_no_content'] = 'Lo siento, no encontré información relevante para responder a tu pregunta. Intenta preguntar de otra manera o vuelve a consultar más tarde.';
$string['parce:usechat'] = 'Usar el chat de Parce';
$string['parce:viewallchats'] = 'Ver todas las conversaciones de chat';
$string['placeholder'] = 'Escribe tu pregunta...';
$string['pluginname'] = 'Parce - Chat PyR';
$string['privacy:metadata:ai_actions'] = 'Trazas técnicas de las solicitudes de IA realizadas por el chat.';
$string['privacy:metadata:ai_actions:contextid'] = 'El contexto desde el que se realizó la solicitud de IA.';
$string['privacy:metadata:ai_actions:conversationentryid'] = 'El turno de conversación relacionado.';
$string['privacy:metadata:ai_actions:conversationkey'] = 'El identificador de la sesión de conversación.';
$string['privacy:metadata:ai_actions:generatedcontent'] = 'El contenido sin procesar generado por el proveedor de IA.';
$string['privacy:metadata:ai_actions:prompt'] = 'Las instrucciones del sistema enviadas al proveedor de IA.';
$string['privacy:metadata:ai_actions:prompttext'] = 'La pregunta, conversación reciente y datos recuperados del curso, de calificaciones o de finalización enviados al proveedor de IA.';
$string['privacy:metadata:ai_actions:technical'] = 'Información técnica de correlación, ciclo de vida, duración, respuesta, errores, modelo, proveedor y uso de tokens.';
$string['privacy:metadata:ai_actions:timecreated'] = 'El momento en que se creó la solicitud de IA.';
$string['privacy:metadata:ai_actions:userid'] = 'El usuario que realizó la solicitud.';
$string['privacy:metadata:aiprovider'] = 'El proveedor de IA configurado recibe preguntas, el contexto reciente y datos relevantes del curso, de calificaciones visibles o de finalización para generar respuestas.';
$string['privacy:metadata:conversation_entries'] = 'Turnos completados de las conversaciones del chat.';
$string['privacy:metadata:conversation_entries:chatid'] = 'El contexto de Moodle en el que tuvo lugar la conversación.';
$string['privacy:metadata:conversation_entries:conversationkey'] = 'El identificador de la sesión de conversación.';
$string['privacy:metadata:conversation_entries:question'] = 'La pregunta enviada por el usuario.';
$string['privacy:metadata:conversation_entries:response'] = 'La respuesta mostrada al usuario.';
$string['privacy:metadata:conversation_entries:timecreated'] = 'El momento en que se creó el turno de conversación.';
$string['privacy:metadata:conversation_entries:userid'] = 'El usuario que participó en la conversación.';
$string['resource_results'] = 'De acuerdo con tu solicitud, encontré estos recursos:';
$string['setting_ai_instructions_heading'] = 'Instrucciones para Acciones de IA';
$string['setting_ai_instructions_heading_desc'] = 'Configura las instrucciones del sistema para cada acción de IA. Estas instrucciones guían el comportamiento del modelo de IA para tipos específicos de preguntas.';
$string['setting_allowopenanswer'] = 'Permitir Respuestas Abiertas';
$string['setting_allowopenanswer_desc'] = 'Permitir que la respuesta se busque de manera abierta en IA, sin depender de los resultados de búsqueda de contenido. Esto puede proporcionar respuestas más directas pero puede ser menos preciso y más propenso a producir respuestas irrelevantes. Úsalo con precaución.';
$string['setting_answer_question_prompt'] = 'Instrucción para Responder Preguntas';
$string['setting_answer_question_prompt_desc'] = 'La instrucción del sistema que guía a la IA al responder directamente las preguntas de los usuarios. Esta instrucción se envía al modelo de IA con cada solicitud de respuesta a preguntas.';
$string['setting_cache_heading'] = 'Configuración de Caché de conversaciones';
$string['setting_cache_heading_desc'] = 'Configura la conversación activa almacenada en el caché de sesión. El historial persistente no se carga en este caché ni en los prompts de IA.';
$string['setting_cache_maxentries'] = 'Máximo de turnos por conversación';
$string['setting_cache_maxentries_desc'] = 'Límite de seguridad para turnos completos de pregunta y respuesta. Ingrese un valor entre 1 y el máximo estricto de 40. Al alcanzarlo se inicia una conversación nueva.';
$string['setting_chat_title'] = 'Título de la Ventana de Chat';
$string['setting_chat_title_desc'] = 'El título que se muestra en el encabezado de la ventana de chat';
$string['setting_conversation_maxtokens'] = 'Límite de tokens estimados por conversación';
$string['setting_conversation_maxtokens_desc'] = 'Límite principal de una conversación activa. Ingrese un valor entre 1 y el máximo estricto de 16.000. La estimación conservadora usa un token por cada tres caracteres Unicode.';
$string['setting_enable_guests'] = 'Habilitar para Usuarios Invitados';
$string['setting_enable_guests_desc'] = 'Permitir que los usuarios invitados accedan al widget de chat';
$string['setting_enabled'] = 'Habilitar Widget de Chat Parce';
$string['setting_enabled_desc'] = 'Cuando está habilitado, el widget de chat flotante aparecerá en todas las páginas para usuarios registrados';
$string['setting_history_context_limit'] = 'Máximo de contextos del historial';
$string['setting_history_context_limit_desc'] = 'Cantidad máxima de contextos mostrados en el historial. Ingrese un valor entre 1 y 100.';
$string['setting_history_conversation_limit'] = 'Máximo de conversaciones por contexto';
$string['setting_history_conversation_limit_desc'] = 'Cantidad máxima de conversaciones cargadas al desplegar un contexto. Ingrese un valor entre 1 y 100.';
$string['setting_history_heading'] = 'Interfaz de históricos';
$string['setting_history_heading_desc'] = 'Configura los tamaños máximos de resultados para la navegación y búsqueda del historial.';
$string['setting_history_search_limit'] = 'Máximo de resultados de búsqueda';
$string['setting_history_search_limit_desc'] = 'Cantidad máxima de conversaciones coincidentes retornadas por una búsqueda. Ingrese un valor entre 1 y 100.';
$string['setting_openanswer_prompt'] = 'Instrucción para Respuestas Abiertas';
$string['setting_openanswer_prompt_desc'] = 'La instrucción del sistema que guía a la IA al responder preguntas de los usuarios sin restricciones de búsqueda de contenido. Esta instrucción se envía al modelo de IA con cada solicitud de respuesta abierta.';
$string['setting_question_plan_prompt'] = 'Instrucción para entender las Preguntas';
$string['setting_question_plan_prompt_desc'] = 'La instrucción del sistema que guía a la IA al crear un plan o esquema estructurado para una pregunta o tema. Esta instrucción se envía al modelo de IA con cada solicitud de plan de preguntas.';
$string['static_help'] = 'ayuda';
$string['yesterday'] = 'Ayer {$a}';
