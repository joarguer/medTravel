# Modelo de Negocio MedTravel - Actualización 2026

**Fecha de actualización:** 29 de enero de 2026  
**Versión:** 2.0

---

## 📋 Resumen Ejecutivo

MedTravel es una plataforma de turismo médico que conecta pacientes ubicados en Estados Unidos (principalmente Florida) con servicios médicos de alta calidad en el Quindío, Colombia.

### Propuesta de Valor
- **Ahorro de costos:** 60-80% menos que en USA
- **Servicio integral:** Gestión completa de viaje, estadía y servicios médicos
- **Atención bilingüe:** Soporte en español e inglés
- **Calidad garantizada:** Proveedores médicos verificados y certificados

---

## 🌎 Modelo Operativo

### Ubicaciones y Operación

#### **Origen: Florida, USA**
- Personal de MedTravel ubicado en Florida
- Atención inicial al cliente
- Coordinación de servicios
- Canales de comunicación principal

#### **Destino: Quindío, Colombia**
- Red de clínicas y médicos certificados
- Servicios médicos especializados
- Infraestructura hotelera y turística
- Coordinación local de citas y tratamientos

### Flujo Operativo

```
Cliente (Florida) 
    ↓
Contacto inicial (Widget/WhatsApp/Teléfono/Email)
    ↓
Consulta médica virtual (evaluación)
    ↓
Cotización de paquete todo incluido:
    • Servicio médico
    • Vuelo ida/vuelta
    • Hotel
    • Transporte local
    • Alimentación
    ↓
Confirmación y pago
    ↓
Coordinación de viaje
    ↓
Llegada a Colombia (Quindío)
    ↓
Transporte a hotel
    ↓
Citas médicas programadas
    ↓
Tratamiento/Procedimiento
    ↓
Recuperación y estadía
    ↓
Seguimiento post-tratamiento
    ↓
Retorno a USA
    ↓
Follow-up remoto
```

---

## 🎯 Servicios Ofrecidos

### 1. Servicios Médicos (Core)
- Cirugías estéticas
- Odontología especializada
- Procedimientos ortopédicos
- Tratamientos de fertilidad
- Cirugías bariátricas
- Otros procedimientos especializados

### 2. Servicios de MedTravel (Gestión Integral)

#### ✈️ Gestión de Vuelos
- Búsqueda y reserva de vuelos
- Coordinación de fechas según tratamiento
- Apoyo con documentación de viaje
- Gestión de cambios o cancelaciones

#### 🏨 Alojamiento
- Reserva de hoteles cercanos a clínicas
- Opciones según presupuesto del cliente
- Coordinación check-in/check-out
- Hoteles con facilidades para recuperación

#### 🚗 Transporte Local
- Aeropuerto → Hotel
- Hotel → Clínica (citas médicas)
- Clínica → Hotel
- Hotel → Aeropuerto
- Transporte para acompañantes

#### 🍽️ Alimentación
- Planes de alimentación según necesidades médicas
- Restricciones dietéticas post-operatorias
- Opciones vegetarianas/veganas
- Coordinación con hoteles/restaurantes

#### 🏥 Coordinación Médica
- Agendamiento de citas
- Traducción español-inglés en consultas
- Acompañamiento a procedimientos
- Seguimiento post-tratamiento
- Gestión de historial médico

---

## 📞 Canales de Comunicación

### Prioridad de Canales

#### 1. **Widget Web (PRINCIPAL)** 🔴
- Integrado en todas las páginas del sitio
- Servicio: ConnectarBot (ya implementado)
- Chat en tiempo real
- Captura de leads automática
- Enrutamiento a WhatsApp

#### 2. **WhatsApp Business** 🟡
- Principal canal de seguimiento
- Número de Florida
- Respuestas automatizadas iniciales
- Atención personalizada
- Envío de cotizaciones, confirmaciones
- Recordatorios de citas

#### 3. **Teléfono** 🟢
- Número en Florida: +1 (561) 698-8069
- Atención en horario laboral USA
- Para consultas urgentes
- Seguimiento de casos complejos

#### 4. **Email** ⚪ (Secundario)
- info@medtravel.com.co
- Envío de documentación
- Confirmaciones formales
- Contratos y facturas

---

## 👥 Actores del Sistema

### 1. Clientes/Pacientes (USA)
**Perfil:**
- Residentes en USA (principalmente Florida)
- Buscan procedimientos médicos de calidad a menor costo
- Requieren servicio integral en español o inglés
- Dispuestos a viajar a Colombia

**Necesidades:**
- Información clara de costos
- Confianza en proveedores médicos
- Soporte logístico completo
- Comunicación fluida

### 2. Personal MedTravel (Florida)
**Roles:**
- Coordinadores de casos
- Asesores médicos
- Gestores de viaje
- Atención al cliente

**Responsabilidades:**
- Contacto inicial con clientes
- Evaluación de necesidades
- Cotización de paquetes
- Coordinación de servicios
- Seguimiento continuo

### 3. Proveedores Médicos (Colombia - Quindío)
**Tipos:**
- Clínicas especializadas
- Médicos independientes
- Laboratorios
- Centros de diagnóstico

**Responsabilidades:**
- Prestación de servicios médicos
- Gestión de su calendario
- Actualización de disponibilidad
- Comunicación de resultados
- Seguimiento post-tratamiento

### 4. Administradores del Sistema
**Roles:**
- Admin Global (acceso total)
- Supervisores
- Soporte técnico

**Responsabilidades:**
- Gestión de usuarios
- Configuración del sitio
- Reportes y métricas
- Mantenimiento del sistema

---

## 💻 Arquitectura del Sistema

### Frontend (Público)
```
/medtravel/
├── index.php              # Homepage con carrusel
├── about.php              # Quiénes somos
├── offers.php             # Catálogo de servicios médicos
├── offer_detail.php       # Detalle de servicio
├── contact.php            # Contacto
├── inc/
│   └── include.php        # Configuración frontend
├── js/
│   ├── main.js           # Lógica general
│   └── about.js          # Scripts por página
└── css/
    └── style.css
```

### Backend Administrativo
```
/admin/
├── index.php                    # Dashboard principal
├── clientes.php                 # CRM de pacientes (NUEVO)
├── agendamiento.php             # Google Calendar (NUEVO)
├── paquetes.php                 # Gestión de paquetes (NUEVO)
├── providers.php                # Gestión de clínicas/médicos
├── provider_offers.php          # Ofertas de servicios
├── service_catalog.php          # Catálogo de servicios
├── service_categories.php       # Categorías
├── home_edit.php               # Editor de homepage
├── about_edit.php              # Editor de about
├── services_edit.php           # Editor de services
├── offer_detail_edit.php       # Editor de offer detail
├── crear_usuario.php           # Gestión de usuarios
├── mi_empresa.php              # Perfil de prestador
├── mis_datos.php               # Datos personales
├── include/
│   ├── include.php             # Configuración admin
│   ├── conexion.php            # Conexión BD
│   ├── valida_session.php      # Autenticación
│   └── log.php                 # Sistema de login
├── ajax/
│   ├── clientes.php            # API clientes (NUEVO)
│   ├── agendamiento.php        # API calendar (NUEVO)
│   ├── paquetes.php            # API paquetes (NUEVO)
│   ├── google_calendar_api.php # Integración Google (NUEVO)
│   ├── providers.php           # API proveedores
│   ├── provider_offers.php     # API ofertas
│   ├── service_catalog.php     # API catálogo
│   └── ...
└── js/
    ├── clientes.js             # Lógica CRM (NUEVO)
    ├── agendamiento.js         # Lógica calendar (NUEVO)
    ├── paquetes.js             # Lógica paquetes (NUEVO)
    ├── providers.js            # Lógica proveedores
    └── global_scripts.js       # Scripts globales
```

---

## 🗄️ Base de Datos - Nuevas Tablas

### 1. Tabla: `clientes`
Gestión de pacientes/contratantes del servicio.

```sql
CREATE TABLE clientes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    -- Información personal
    nombre VARCHAR(200) NOT NULL,
    apellido VARCHAR(200) NOT NULL,
    email VARCHAR(200) NOT NULL UNIQUE,
    telefono VARCHAR(50),
    whatsapp VARCHAR(50),
    fecha_nacimiento DATE,
    
    -- Ubicación
    pais VARCHAR(100) DEFAULT 'USA',
    estado VARCHAR(100),
    ciudad VARCHAR(200),
    
    -- Documentación
    numero_pasaporte VARCHAR(100),
    tipo_documento ENUM('passport','license','id') DEFAULT 'passport',
    
    -- Información médica
    condiciones_medicas TEXT,
    alergias TEXT,
    medicamentos_actuales TEXT,
    
    -- Contacto de emergencia
    contacto_emergencia_nombre VARCHAR(200),
    contacto_emergencia_telefono VARCHAR(50),
    contacto_emergencia_relacion VARCHAR(100),
    
    -- Estado del cliente
    status ENUM('lead','cotizado','confirmado','en_viaje','post_tratamiento','finalizado','inactivo') DEFAULT 'lead',
    origen_contacto ENUM('web','whatsapp','telefono','email','referido','redes_sociales') DEFAULT 'web',
    
    -- Notas
    notas TEXT,
    
    -- Auditoría
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2. Tabla: `appointments`
Gestión de citas médicas con sincronización Google Calendar.

```sql
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Relaciones
    client_id INT NOT NULL,
    provider_id INT NOT NULL,
    service_id INT,
    
    -- Fecha y hora
    appointment_datetime DATETIME NOT NULL,
    duration_minutes INT DEFAULT 60,
    timezone VARCHAR(50) DEFAULT 'America/Bogota',
    
    -- Google Calendar
    google_event_id VARCHAR(255),
    google_calendar_id VARCHAR(255),
    sync_status ENUM('pending','synced','error') DEFAULT 'pending',
    last_sync_at DATETIME,
    
    -- Estado
    status ENUM('pending','confirmed','in_progress','completed','cancelled','no_show') DEFAULT 'pending',
    cancellation_reason TEXT,
    
    -- Detalles
    appointment_type ENUM('consultation','procedure','follow_up','lab','other') DEFAULT 'consultation',
    location VARCHAR(255),
    notes TEXT,
    
    -- Notificaciones
    reminder_sent BOOLEAN DEFAULT 0,
    reminder_sent_at DATETIME,
    
    -- Auditoría
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (client_id) REFERENCES clientes(id),
    FOREIGN KEY (provider_id) REFERENCES providers(id),
    FOREIGN KEY (service_id) REFERENCES provider_service_offers(id),
    FOREIGN KEY (created_by) REFERENCES usuarios(id),
    
    INDEX idx_appointment_date (appointment_datetime),
    INDEX idx_provider (provider_id),
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3. Tabla: `travel_packages`
Paquetes todo incluido (vuelo, hotel, transporte, alimentación).

```sql
CREATE TABLE travel_packages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Relaciones
    client_id INT NOT NULL,
    appointment_id INT,
    
    -- Información general
    package_name VARCHAR(255),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    
    -- VUELO
    flight_included BOOLEAN DEFAULT 0,
    flight_airline VARCHAR(100),
    flight_departure_airport VARCHAR(50),
    flight_arrival_airport VARCHAR(50) DEFAULT 'Armenia (AXM)',
    flight_departure_date DATE,
    flight_departure_time TIME,
    flight_arrival_date DATE,
    flight_arrival_time TIME,
    flight_return_date DATE,
    flight_return_time TIME,
    flight_confirmation_number VARCHAR(100),
    flight_cost DECIMAL(10,2),
    
    -- HOTEL
    hotel_included BOOLEAN DEFAULT 0,
    hotel_name VARCHAR(200),
    hotel_address TEXT,
    hotel_city VARCHAR(100) DEFAULT 'Quindío',
    hotel_phone VARCHAR(50),
    hotel_checkin_date DATE,
    hotel_checkout_date DATE,
    hotel_room_type VARCHAR(100),
    hotel_confirmation_number VARCHAR(100),
    hotel_nights INT,
    hotel_cost DECIMAL(10,2),
    
    -- TRANSPORTE
    transport_included BOOLEAN DEFAULT 0,
    transport_type ENUM('taxi','rental_car','private_driver','van','shuttle') DEFAULT 'private_driver',
    transport_details TEXT,
    transport_pickup_times JSON,
    transport_cost DECIMAL(10,2),
    
    -- ALIMENTACIÓN
    meals_included BOOLEAN DEFAULT 0,
    meals_plan ENUM('breakfast_only','half_board','full_board','all_inclusive') DEFAULT 'breakfast_only',
    dietary_restrictions TEXT,
    meals_cost DECIMAL(10,2),
    
    -- COSTOS
    medical_service_cost DECIMAL(10,2),
    additional_services_cost DECIMAL(10,2) DEFAULT 0,
    total_package_cost DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    
    -- PAGOS
    payment_status ENUM('pending','deposit_paid','partial_paid','fully_paid','refunded') DEFAULT 'pending',
    amount_paid DECIMAL(10,2) DEFAULT 0,
    balance_due DECIMAL(10,2),
    payment_method VARCHAR(100),
    payment_notes TEXT,
    
    -- ESTADO
    status ENUM('quoted','confirmed','in_progress','completed','cancelled') DEFAULT 'quoted',
    
    -- NOTAS
    special_requests TEXT,
    internal_notes TEXT,
    
    -- Auditoría
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (client_id) REFERENCES clientes(id),
    FOREIGN KEY (appointment_id) REFERENCES appointments(id),
    FOREIGN KEY (created_by) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4. Tabla: `client_documents`
Almacenamiento de documentos del cliente.

```sql
CREATE TABLE client_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    
    -- Tipo de documento
    document_type ENUM('passport','medical_history','lab_results','prescription','invoice','contract','consent_form','photos','other') DEFAULT 'other',
    document_category VARCHAR(100),
    
    -- Archivo
    file_path VARCHAR(500) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    file_size INT,
    mime_type VARCHAR(100),
    
    -- Metadata
    description TEXT,
    document_date DATE,
    expiration_date DATE,
    
    -- Auditoría
    uploaded_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (client_id) REFERENCES clientes(id),
    FOREIGN KEY (uploaded_by) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 5. Tabla: `notifications`
Sistema de notificaciones automáticas.

```sql
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Destinatario
    recipient_type ENUM('client','provider','admin') NOT NULL,
    recipient_id INT NOT NULL,
    recipient_email VARCHAR(200),
    recipient_phone VARCHAR(50),
    
    -- Tipo de notificación
    notification_type ENUM('appointment_reminder','appointment_confirmation','payment_confirmation','package_details','follow_up','general') NOT NULL,
    
    -- Canales
    channel ENUM('email','whatsapp','sms','system') NOT NULL,
    
    -- Contenido
    subject VARCHAR(255),
    message TEXT NOT NULL,
    
    -- Estado
    status ENUM('pending','sent','delivered','failed','read') DEFAULT 'pending',
    sent_at DATETIME,
    delivered_at DATETIME,
    read_at DATETIME,
    error_message TEXT,
    
    -- Relacionado
    related_type ENUM('appointment','package','client','other'),
    related_id INT,
    
    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 🔐 Roles y Permisos

### Roles del Sistema

#### 1. **Admin Global** (rol = 1, ppal = 1)
**Acceso completo a:**
- ✅ Todos los módulos
- ✅ Gestión de usuarios
- ✅ Todos los clientes
- ✅ Todos los proveedores
- ✅ Todos los paquetes
- ✅ Todas las citas
- ✅ Reportes globales
- ✅ Configuración del sitio

#### 2. **Coordinador MedTravel** (rol = 2 - NUEVO)
**Acceso a:**
- ✅ Gestión de clientes (CRM completo)
- ✅ Creación de paquetes
- ✅ Agendamiento de citas
- ✅ Notificaciones a clientes
- ✅ Documentos de clientes
- ✅ Reportes de sus casos
- ❌ Configuración del sitio
- ❌ Gestión de usuarios
- ❌ Finanzas globales

#### 3. **Prestador/Clínica** (rol = 4, tiene provider_id)
**Acceso a:**
- ✅ Su propio calendario (Google Calendar)
- ✅ Sus propias citas
- ✅ Clientes asignados a él
- ✅ Gestión de sus servicios/ofertas
- ✅ Mi Empresa (perfil)
- ✅ Mis Datos
- ❌ Otros proveedores
- ❌ Paquetes completos
- ❌ Otros clientes
- ❌ Configuración del sitio

#### 4. **Soporte** (rol = 3 - NUEVO)
**Acceso de solo lectura a:**
- ✅ Ver clientes
- ✅ Ver citas
- ✅ Ver paquetes
- ❌ Modificar datos sensibles
- ❌ Eliminar registros

---

## 📊 KPIs y Métricas

### Métricas Principales

1. **Conversión**
   - Leads generados/mes
   - Tasa de conversión (lead → cliente)
   - Tiempo promedio de conversión
   - Fuente de leads más efectiva

2. **Operacional**
   - Citas programadas/mes
   - Citas completadas vs canceladas
   - Tiempo promedio de tratamiento
   - Satisfacción del cliente (NPS)

3. **Financiero**
   - Ingresos mensuales
   - Ticket promedio por paquete
   - Costo promedio de adquisición
   - Margen por servicio

4. **Proveedores**
   - Proveedores activos
   - Servicios más solicitados por proveedor
   - Rating promedio por proveedor
   - Tiempo de respuesta

---

## 🚀 Roadmap de Implementación

### FASE 1: CRM y Agendamiento (Semanas 1-2) 🔴 CRÍTICO
- [x] Documentación del modelo de negocio
- [ ] Crear tabla `clientes`
- [ ] Módulo `admin/clientes.php` (CRUD completo)
- [ ] API `admin/ajax/clientes.php`
- [ ] Frontend JS `admin/js/clientes.js`
- [ ] Crear tabla `appointments`
- [ ] Integración Google Calendar API
- [ ] Módulo `admin/agendamiento.php`
- [ ] API `admin/ajax/agendamiento.php`
- [ ] API `admin/ajax/google_calendar_api.php`
- [ ] Frontend JS `admin/js/agendamiento.js`

### FASE 2: Paquetes y Notificaciones (Semanas 3-4) 🟡 ALTA
- [ ] Crear tabla `travel_packages`
- [ ] Módulo `admin/paquetes.php`
- [ ] API `admin/ajax/paquetes.php`
- [ ] Frontend JS `admin/js/paquetes.js`
- [ ] Crear tabla `notifications`
- [ ] Sistema de notificaciones automáticas
- [ ] Integración con email (PHPMailer)
- [ ] Integración con WhatsApp (ConnectarBot)
- [ ] Templates de emails
- [ ] Cron jobs para recordatorios

### FASE 3: Documentos y Reportes (Semanas 5-6) 🟢 MEDIA
- [ ] Crear tabla `client_documents`
- [ ] Módulo `admin/documentos.php`
- [ ] Upload seguro de archivos
- [ ] Visor de documentos
- [ ] Dashboard de métricas
- [ ] Reportes de conversión
- [ ] Reportes financieros
- [ ] Exportación a Excel/PDF

### FASE 4: Frontend Público (Semanas 7-8) 🟢 MEDIA
- [ ] Sección "How It Works" en index.php
- [ ] Página "All-Inclusive Packages"
- [ ] Calculadora de ahorros
- [ ] FAQ ampliado
- [ ] Testimonios en video
- [ ] Landing pages para campañas
- [ ] Optimización SEO

---

## 🔧 Integraciones Técnicas

### 1. Google Calendar API
**Propósito:** Sincronización bidireccional de citas.

**Configuración necesaria:**
- Google Cloud Project
- OAuth 2.0 credentials
- Calendar API habilitada
- Composer: `google/apiclient`

**Flujo:**
```
Admin crea cita → API crea evento en Google Calendar → Google Event ID guardado en BD
Google Calendar actualizado → Webhook notifica → BD actualizada
```

### 2. ConnectarBot (Widget + WhatsApp)
**Propósito:** Canal principal de comunicación con clientes.

**Ya implementado:**
- Widget en todas las páginas
- Captura de leads
- Enrutamiento a WhatsApp

**Por integrar:**
- API para envío de notificaciones programadas
- Templates de mensajes
- Integración con tabla `notifications`

### 3. PHPMailer / SendGrid
**Propósito:** Envío de emails transaccionales y marketing.

**Tipos de emails:**
- Confirmación de reserva
- Detalles de paquete (vuelo, hotel)
- Recordatorios de cita
- Encuestas post-servicio
- Follow-up

---

## 📈 Ventajas Competitivas

1. **Servicio Integral:** No solo medicina, sino experiencia completa
2. **Atención Bilingüe:** Personal que habla inglés y español
3. **Ahorro Significativo:** 60-80% vs precios USA
4. **Calidad Certificada:** Proveedores verificados
5. **Soporte 24/7:** Antes, durante y después del viaje
6. **Tecnología:** Plataforma digital moderna
7. **Transparencia:** Costos claros desde el inicio
8. **Flexibilidad:** Paquetes personalizables

---

## 🎯 Objetivos 2026

### Q1 (Enero - Marzo)
- ✅ Implementar CRM completo
- ✅ Integrar Google Calendar
- ✅ Lanzar sistema de paquetes
- 🎯 Meta: 20 clientes/mes

### Q2 (Abril - Junio)
- 🎯 Optimizar conversión web
- 🎯 Ampliar red de proveedores (15 clínicas)
- 🎯 Campañas en redes sociales
- 🎯 Meta: 50 clientes/mes

### Q3 (Julio - Septiembre)
- 🎯 Expansión a otros estados USA
- 🎯 Nuevos destinos en Colombia
- 🎯 App móvil (fase 1)
- 🎯 Meta: 100 clientes/mes

### Q4 (Octubre - Diciembre)
- 🎯 Programa de referidos
- 🎯 Membresías anuales
- 🎯 Alianzas con seguros
- 🎯 Meta: 150 clientes/mes

---

## 📞 Contacto y Soporte

**MedTravel Florida Office**
- Teléfono: +1 (561) 698-8069
- Email: info@medtravel.com.co
- WhatsApp: [Número de Florida]
- Web: https://medtravel.com.co

**Horario de Atención:**
- Lunes a Viernes: 9:00 AM - 6:00 PM (EST)
- Sábados: 10:00 AM - 2:00 PM (EST)
- Emergencias 24/7 para clientes en viaje

---

## 📝 Notas de Implementación

### Convenciones de Código
- **PHP:** PSR-12, camelCase para variables
- **JavaScript:** ES6+, camelCase
- **SQL:** snake_case para tablas y columnas
- **CSS:** BEM methodology

### Seguridad
- ✅ Validación de inputs (frontend y backend)
- ✅ Prepared statements (mysqli)
- ✅ CSRF tokens en formularios
- ✅ Sanitización de salidas
- ✅ HTTPS obligatorio
- ✅ Backups diarios de BD

### Performance
- ✅ Índices en columnas frecuentes
- ✅ Caché de queries comunes
- ✅ Lazy loading de imágenes
- ✅ Minificación de assets
- ✅ CDN para archivos estáticos

---

**Última actualización:** 29 de enero de 2026  
**Documento creado por:** GitHub Copilot AI Assistant  
**Revisado por:** Equipo MedTravel
