# Normas y protocolos de SAVA

Sistema de Asistencia y Validaciones Académicas (Laravel). Este documento describe las normas que el sistema aplica en el código. No existe un manual aparte (`CONTRIBUTING`, reglas de Cursor ni un protocolo institucional en texto): las reglas viven en políticas, enums y clases de soporte.

Unidad: Facultad de Ciencias de la Vida y Tecnologías (FACIVITEC).

## 1. Roles y capacidades

Roles:

| Rol | Código |
|---|---|
| Superusuario | `superusuario` |
| Decano | `decano` |
| Secretaría | `secretaria` |
| Administrativo | `administrativo` |
| Docente | `docente` |
| Código de trabajo | `mantenimiento` |

Capacidades y quién las tiene de origen:

| Capacidad | Quién la tiene de origen |
|---|---|
| Generar solicitudes | Todos los roles |
| Revisar solicitudes | Decano y secretaría |
| Firmar o dar visto bueno | Decano |
| Gestionar usuarios | Decano |
| Emitir notificaciones de faltas (Talento Humano) | Nadie por defecto; se otorga por delegación. Decano y superusuario pueden gestionarlas igual |

El superusuario tiene todas las capacidades.

### Delegación

- El decano puede otorgar: aprobar solicitudes, emitir notificaciones de faltas, revisar solicitudes y generar solicitudes.
- El superusuario puede otorgar cualquier capacidad.
- No se delega al superusuario ni a uno mismo.
- Crear usuarios, importar CSV y restablecer contraseñas queda solo en el superusuario.
- Editar usuarios exige la capacidad de gestionar usuarios.

## 2. Acceso

1. El ingreso es con Microsoft 365 (Azure AD).
2. El correo debe existir en la tabla `users` y la cuenta debe estar activa.
3. El correo debe ser institucional. Se rechazan dominios temporales (mailinator, guerrillamail, yopmail y similares). Si hay dominios permitidos configurados, el correo tiene que pertenecer a uno de ellos.
4. Solo puede haber una sesión activa por usuario: al iniciar sesión se cierran las demás.
5. Quien no tiene cuenta puede pedir acceso. El decano aprueba esa petición y con eso se crea el usuario. El login sigue siendo solo por Office 365.
6. La cédula debe ser ecuatoriana válida: 10 dígitos, provincia 01–24 o 30, tercer dígito menor que 6 y dígito verificador correcto.
7. Si se usa contraseña local, debe tener más de 8 caracteres, una mayúscula, una minúscula, un número y un carácter especial.

## 3. Protocolo de una solicitud

### Estado inicial según quién la crea

| Quién crea | Estado al crearse |
|---|---|
| Docente, administrativo o código de trabajo | En revisión (Secretaría) |
| Secretaría | Pendiente de aprobación del decano |
| Decano | Aprobada |

### Revisión y aprobación

1. Secretaría revisa lo que está en revisión.
2. Si rechaza, la solicitud queda rechazada.
3. Si aprueba, pasa a pendiente del decano.
4. Queda aprobada de inmediato si el autor es el decano o si quien revisa firma como decano.
5. El decano aprueba o rechaza lo que está pendiente de su visto bueno.

Estados posibles: en borrador, en revisión (Secretaría), pendiente de aprobación del decano, aprobada, rechazada.

### Quién puede ver, editar, borrar y firmar

- **Ver:** el autor, quien tiene capacidad de revisar o quien tiene capacidad de aprobar.
- **Crear:** quien tiene capacidad de generar solicitudes.
- **Editar:** el autor, mientras está en borrador o en revisión de Secretaría. Quien revisa o aprueba también puede editar durante la revisión de Secretaría. No se edita si está aprobada, rechazada o pendiente del decano.
- **Borrar:** solo el autor. En borrador, siempre. En revisión de Secretaría, solo si Secretaría todavía no la revisó.
- **Firmar el oficio:** el autor, en borrador, en revisión o ya aprobada. Si el autor es secretaría, también cuando está pendiente del decano.

### Requisitos previos

- Antes de crear una solicitud, el perfil debe tener cédula y carrera.
- La fecha de inicio no puede ser anterior a tres días.

## 4. Reglas por tipo de trámite

### Permiso

Ausencia por horas en un solo día.

- La fecha de inicio y la de fin son el mismo día.
- Hay que indicar hora de inicio y hora de fin. La fin debe ser posterior al inicio.
- Motivos admitidos: reunión, salida por prácticas o vinculación, asuntos familiares, trámites personales, cita médica u otra opción.
- Si el motivo es «otra opción», hay que escribir el motivo.
- La cita médica exige subir la orden o citación que verifique la cita en ese horario.

### Justificación por atraso

- Solo se registra para la fecha de hoy.
- Hay que indicar la hora establecida de llegada y la hora real. La real debe ser posterior a la establecida.
- Motivos: avería de transporte, problemas viales o cuidado de dependientes (familiar hasta primer grado).

### Visitas de campo

Justificación por visitas técnicas, congresos, capacitaciones u otras actividades fuera de la ciudad o del país.

- Hay que indicar inicio de la falta y fecha de retorno. El retorno no puede ser anterior al inicio.
- El evento debe caer dentro de esas fechas: no puede empezar antes de la visita ni terminar después del retorno.
- Hay que indicar tipo de visita, nombre del evento o estudio y lugar (ciudad, país). El rol específico es opcional.
- Día, mes y año del evento deben formar una fecha real. El año va entre 2000 y 2100.

### Certificado de salud

Justifica ausencia por enfermedad. El anexo (justificante médico) es obligatorio.

### Calamidad doméstica

Emergencias o situaciones familiares graves. El anexo es obligatorio (certificado de defunción, informe médico, constancia hospitalaria u otro soporte).

### Reporte de novedad en marcación

Olvido de marcación o falla del sistema Face ID.

- Hay que indicar la jornada y la fecha del incidente.
- El tipo de marcación omitida es entrada, salida o ambas.
- Si falta la entrada, hay que indicar la hora real de ingreso. Si falta la salida, la hora real de salida. Si faltan las dos, ambas horas, y la salida debe ser posterior al ingreso.
- Motivos: olvido de marcación o falla técnica del Face ID.

### Adjuntos

- Hasta 10 archivos en total, incluido el documento que genera el sistema.
- Peso total máximo: 10 MB.
- Formatos: PDF, PNG o JPG.

## 5. Matriz de novedades (Talento Humano)

La jornada se cuenta como 480 minutos (8 horas). Un permiso o un atraso se miden por las horas indicadas. El resto de trámites se cuentan por días completos de jornada.

Columna de «tipo de novedad»:

| Caso | Columna |
|---|---|
| Permiso por horas | K |
| Permiso tratado como licencia | J |
| Atraso justificado | N |
| Otro trámite ya aprobado | Q |
| Vacaciones, feriados, salidas injustificadas, atraso injustificado, desvinculación y falta injustificada | Vacías |

Régimen laboral según el rol del autor:

| Rol | Régimen | Puesto |
|---|---|---|
| Docente | Docente | docente |
| Decano | Docente | decano |
| Código de trabajo | Código de trabajo | mantenimiento |
| Secretaría | Administrativo | secretaria |
| Administrativo | Administrativo | personal tecnológico |
| Superusuario | Administrativo | (sin puesto) |

## 6. Notificaciones de faltas

Emitir notificaciones de faltas hacia Talento Humano pueden hacerlo el superusuario, el decano o quien tenga esa capacidad delegada.

## 7. Dónde está cada norma en el código

| Norma | Archivo |
|---|---|
| Roles y capacidades por defecto | `app/Enums/AppRole.php`, `app/Enums/CapabilityType.php` |
| Quién ve, edita, borra, revisa y aprueba | `app/Policies/SolicitudPolicy.php` |
| Gestión de usuarios y delegación | `app/Policies/UserPolicy.php`, `app/Support/DelegacionDecano.php` |
| Estado inicial y paso tras la revisión | `app/Services/SolicitudWorkflowService.php` |
| Tope de 3 días, perfil y anexo obligatorio | `app/Support/SolicitudValidator.php` |
| Permiso | `app/Support/SolicitudPermisoRules.php`, `app/Enums/PermisoMotivo.php` |
| Atraso | `app/Support/SolicitudJustificacionAtrasoRules.php` |
| Visita de campo | `app/Support/SolicitudViajeRules.php` |
| Marcación | `app/Support/SolicitudFaltaMarcadoRules.php` |
| Adjuntos | `app/Support/SolicitudAdjuntoLimits.php` |
| Matriz de Talento Humano | `app/Support/MatrizNovedades.php` |
| Correo institucional | `app/Support/InstitutionalEmail.php` |
| Cédula | `app/Support/CedulaEcuador.php` |
| Contraseña | `app/Rules/StrongPassword.php` |
| Una sola sesión | `app/Support/SingleSessionEnforcer.php` |
| Notificaciones de faltas | `app/Support/NotificacionFaltaAccess.php` |
