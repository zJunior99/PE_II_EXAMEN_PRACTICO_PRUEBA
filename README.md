# 🚀 Ruta Inteligente TI

## Estudiantes
- Nestor Serrano Ibañez
- Junior Mamani Estaña

## 🧰 Tecnologías

### 🔙 Backend
- PHP 8.1  
- Patrón MVC (Modelo - Vista - Controlador)

### 🎨 Frontend
- HTML5  
- CSS3  
- Tailwind CSS  
- JavaScript  

### 🗄️ Base de Datos
- PostgreSQL  

### ☁️ Backend as a Service (BaaS)
Se utiliza Supabase para:
- Gestión de base de datos  
- Exposición automática de APIs  
- Autenticación y gestión de usuarios  
- Almacenamiento de archivos  

---

## 🏗️ Arquitectura del Sistema

El sistema está basado en el patrón **MVC**, complementado con un enfoque de **Backend as a Service (BaaS)** mediante Supabase.

Este enfoque permite:
- Separación clara de responsabilidades  
- Mayor escalabilidad  
- Reducción de complejidad en el backend  

---

## 🧩 Arquitectura en Capas

### 🎯 Capa de Presentación
Encargada de la interfaz de usuario y la interacción con el usuario.  
**Tecnologías:** HTML, CSS, Tailwind CSS, JavaScript  

### ⚙️ Capa de Aplicación
Gestiona la lógica de negocio, validaciones y flujo de datos.  
Actúa como intermediaria entre la presentación y los datos.  

### 🔌 Capa de Servicios
Encapsula la comunicación con servicios externos.  
Se encarga de la integración con Supabase.  

### 💾 Capa de Datos
Responsable de la persistencia de la información.  
Gestiona el acceso a la base de datos PostgreSQL.  

---

## 📌 Notas
- Se sigue una arquitectura modular y escalable.  
- Supabase reduce la necesidad de implementar servicios backend complejos desde cero.  
- El uso de MVC facilita el mantenimiento y la organización del código.  

---

## 📋 Requerimientos del Sistema

### 🔹 Requerimientos Funcionales

- **RF01** – Registrar usuarios en el sistema  
- **RF02** – Autenticar usuarios mediante credenciales  
- **RF03** – Gestionar sesiones de usuario  
- **RF04** – Visualizar un dashboard con el resumen del plan estratégico  
- **RF05** – Navegar entre módulos del sistema  
- **RF06** – Registrar una empresa  
- **RF07** – Definir la misión, visión y los valores de la empresa  
- **RF08** – Gestionar objetivos estratégicos  
- **RF09** – Registrar análisis FODA  
- **RF10** – Registrar análisis PEST  
- **RF11** – Registrar análisis de las 5 fuerzas de Porter  
- **RF12** – Generar estrategias a partir del FODA  
- **RF13** – Crear planes de acción (CAME)  
- **RF14** – Guardar la información del plan estratégico en una base de datos  
- **RF15** – Editar información registrada  
- **RF16** – Eliminar información registrada  
- **RF17** – Mostrar un resumen ejecutivo del plan  
- **RF18** – Exportar el plan en formato PDF o Excel  
- **RF19** – Realizar copias de seguridad de la información del sistema  

---

### ⚙️ Requerimientos No Funcionales

- **RNF01 – Seguridad**  
  El sistema debe proteger la información mediante autenticación de usuarios, almacenamiento seguro de contraseñas y uso de conexiones cifradas (HTTPS).

- **RNF02 – Rendimiento**  
  El sistema debe responder en un tiempo máximo de 2 segundos en el 95% de las solicitudes.

- **RNF03 – Adaptabilidad**  
  El sistema debe permitir la incorporación de nuevas funcionalidades sin afectar las existentes mediante una arquitectura modular basada en MVC.

- **RNF04 – Disponibilidad**  
  El sistema debe garantizar una disponibilidad mínima del 99% mensual.

- **RNF05 – Usabilidad**  
  Al menos el 80% de los usuarios debe poder utilizar las funcionalidades principales sin asistencia.

- **RNF06 – Compatibilidad**  
  El sistema debe funcionar correctamente en los navegadores Chrome, Edge y Firefox en sus versiones recientes.

---