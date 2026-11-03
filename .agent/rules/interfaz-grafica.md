---
trigger: always_on
---

# Interfaz gráfica
* Especificación de Diseño y Estilo
Se debe respetar estas especificaciones al momento de generar modulos y nuevas vistas en el sistema.

** Stack Tecnológico: 

Frontend Framework: Tailwind CSS v4 (compilado con binario standalone; ver `resources/css/app.css`). **La plataforma no usa Bootstrap en ninguna forma**: ni el framework, ni sus clases (`col-*`, `d-none`, `form-control`, `table-responsive`…), ni Bootstrap Icons, ni su JavaScript (`data-bs-*`). 

Iconografía: SVG Lucide auto-hospedados en `public/css/icons.css` (`<i class="icon icon-nombre"></i>`). 

** Identidad Visual (Estilo "Executive Clean"): 

Filosofía: Minimalista, profesional, limpio, orientado a negocios. Evita degradados pesados; prefiere superfies planas y sombras suaves. 

Diseñar una arquitectura de estilos para una aplicación web que centralice absolutamente todos los colores y visualizaciones mediante variables (CSS Custom Properties).

Requisitos Estrictos:

Fuente Única de Verdad: Todos los colores deben definirse en un archivo raíz (por ejemplo, :root).

Abstracción Semántica: No quiero nombres de variables basados en el color (ej: --rojo, --azul). Los nombres deben describir la función o la intención (ej: --color-primary, --bg-surface, --text-muted, --border-input).

Inmutabilidad de Módulos: El CSS de los componentes (botones, tarjetas, navbars) nunca debe tener códigos hexadecimales o RGB hardcodeados. Solo deben referenciar las variables semánticas.

Escalabilidad: El sistema debe permitir cambiar todo el "look & feel" (o crear un Dark Mode) simplemente modificando los valores en el archivo raíz, sin tocar una sola línea de código en los módulos de la aplicación.
como datos iniciales de personalización utiliza los siguientes:
Color Primario (Corporativo): #e10e30 (Rojo Intenso). Usar para botones de acción principal (CTA), enlaces activos, bordes de foco y elementos destacados. 

Hover: #b90b26 (Rojo Oscuro). 

Light/Fondo: #fff1f2 (Rojo muy pálido). 

Color Secundario/Neutros: 

Fondo General (Body): #f3f4f6 (Gris claro ejecutivo). NUNCA usar blanco puro #ffffff para el fondo general de la página, solo para tarjetas/contenedores. 

Superficies (Cards): #ffffff (Blanco puro) con sombras suaves (shadow-sm o shadow-lg). 

Texto Principal: #1e293b (Gris oscuro casi negro). 

Texto Secundario: #64748b (Gris azulado medio). 

** Tipografía: 

Familia: 'Inter', sans-serif (auto-hospedada en `public/fonts/inter/`; sin Google Fonts ni CDN). 

Pesos: 300 (Light), 400 (Regular), 500 (Medium), 600 (Semi-Bold), 700 (Bold). 

Legibilidad: Priorizar alto contraste y espaciado generoso. 

** Componentes Clave: 

Tarjetas (Cards): Fondo blanco, border-radius: 16px, box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1). Bordes sutiles o nulos. 

Botones (Primary): Fondo #e10e30, texto blanco, sin borde, border-radius: 8px. Sombra suave al hover. 

Inputs: border-radius: 8px, borde gris claro #e2e8f0. Al foco: borde rojo #e10e30 y anillo de enfoque rojo translúcido. 

** Comportamiento Responsivo (Mobile): 

Navegación: Menú hamburguesa a la izquierda, seguido del Logo. 

Branding Móvil: Ocultar el nombre de la aplicación (`hidden md:inline` de Tailwind) en pantallas pequeñas (<768px) para ahorrar espacio; mostrar solo el isotipo/logo. 

Layout: Contenedores fluidos con padding seguro (20px mínimo).
