# GoFast - Sistema de Mensajería Express

Sistema completo de gestión de mensajería y domicilios desarrollado para WordPress.

## 📋 Descripción

GoFast es una plataforma web que permite a los usuarios cotizar, solicitar y gestionar servicios de mensajería express. Incluye funcionalidades para clientes, mensajeros y administradores.

## 🚀 Características Principales

- **Cotización en tiempo real**: Sistema de cotización con cálculo automático de tarifas y recargos
- **Gestión de pedidos**: Seguimiento completo del estado de los servicios
- **Multi-rol**: Sistema de usuarios con roles (cliente, mensajero, admin)
- **Gestión de negocios**: Los clientes pueden registrar múltiples negocios
- **Panel administrativo**: Dashboard completo con estadísticas y reportes
- **Recargos configurables**: Sistema flexible de recargos fijos y por valor
- **Autenticación persistente**: Sesiones con cookies de 30 días

## 📁 Estructura del Proyecto

```
gofast/
├── db/                          # Scripts SQL de base de datos
│   ├── barrios.sql
│   ├── negocios_gofast.sql
│   ├── recargos.sql
│   ├── recargos_rangos.sql
│   ├── sectores.sql
│   ├── servicios_gofast.sql
│   ├── tarifas.sql
│   └── usuarios_gofast.sql
├── css.css                      # Estilos principales
├── *.code-snippets.json         # Snippets de código (shortcodes)
├── *.php                        # Archivos PHP principales
└── CONFIGURACION_PAGINAS_GOFAST.txt  # Guía de configuración
```

## 🛠️ Instalación

1. **Base de datos**: Ejecutar los scripts SQL en la carpeta `db/` en orden:
   - usuarios_gofast.sql
   - barrios.sql
   - sectores.sql
   - tarifas.sql
   - servicios_gofast.sql
   - negocios_gofast.sql
   - recargos.sql
   - recargos_rangos.sql

2. **Código**: Copiar los snippets de código a tu instalación de WordPress (usando el plugin Code Snippets o directamente en functions.php)

3. **Páginas**: Crear las páginas según `CONFIGURACION_PAGINAS_GOFAST.txt` y asignar los shortcodes correspondientes

4. **Estilos**: Incluir `css.css` en el tema activo

## 📄 Páginas Requeridas

Ver el archivo `CONFIGURACION_PAGINAS_GOFAST.txt` para la lista completa de páginas y shortcodes.

## 🔐 Roles de Usuario

- **Cliente**: Puede cotizar, ver sus pedidos y gestionar negocios
- **Mensajero**: Puede ver pedidos pendientes y asignarse servicios
- **Admin**: Acceso completo al sistema administrativo

## 📊 Base de Datos

El sistema utiliza las siguientes tablas principales:

- `usuarios_gofast`: Usuarios del sistema
- `servicios_gofast`: Pedidos/servicios
- `negocios_gofast`: Negocios registrados
- `barrios`: Barrios de la ciudad
- `sectores`: Sectores para cálculo de tarifas
- `tarifas`: Precios por sector
- `recargos`: Recargos configurables
- `recargos_rangos`: Rangos de recargos variables

## 🎨 Tecnologías

- WordPress (PHP)
- MySQL
- Select2 (para búsqueda de barrios)
- CSS3 (diseño responsive)
- JavaScript (vanilla)

## 📝 Notas

- El sistema requiere WordPress con soporte para sesiones PHP
- Se recomienda usar el plugin "Code Snippets" para gestionar los snippets
- Los estilos están optimizados para móviles y desktop

## 📧 Soporte

Para más información sobre la configuración, consulta `CONFIGURACION_PAGINAS_GOFAST.txt`.

---

**Versión**: 1.0  
**Última actualización**: 2025

