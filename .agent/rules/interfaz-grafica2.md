Actúa como un desarrollador Frontend Senior experto en Tailwind CSS y React. Tu objetivo es replicar la interfaz de "Corporate Admin Pro" siguiendo estas especificaciones estrictas: 

  

### 1. Configuración de Estilos (Tailwind CSS) 

- **Framework**: Tailwind CSS v4, compilado con el binario standalone (`composer tailwind`). Nunca por CDN. **Bootstrap está prohibido** (ni framework, ni clases, ni iconos, ni JS). 

- **Tipografía**: 'Inter', auto-hospedada (`public/css/fonts.css` + `public/fonts/inter/`). No se carga de Google Fonts. 

- **Transiciones**: Aplica una transición global suave: `transition-duration: 300ms; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);`. 

- **Scrollbar**: Personaliza el scrollbar con un track transparente y un thumb de color `slate-200` (light) o `slate-700` (dark), con `border-radius: 10px`. 

  

### 2. Sistema de Temas y Colores 

- **Modo Claro**: Fondo `bg-gray-50`, tarjetas `bg-white`, bordes `border-slate-100`. Texto primario `text-slate-900`. 

- **Modo Oscuro**: Fondo `bg-slate-950`, tarjetas `bg-slate-900`, bordes `border-slate-800`. Texto primario `text-slate-100`. 

- **Color de Marca (Brand)**: Define una variable `--brand-color: #3b82f6`. Úsala para estados `active`, botones primarios (`bg-brand`) y detalles de UI. 

- **Semántica**: Éxito (`emerald-500`), Error (`rose-500`), Advertencia (`orange-500`), Info (`purple-500`). 

  

### 3. Layout y Estructura 

- **Sidebar**: Ancho fijo de `w-60` colapsable a `w-16`. Borde derecho sutil. Ítems de menú con iconos SVG de trazo fino (stroke-width 2). El ítem activo debe tener `bg-brand text-white shadow-md`. 

- **Header**: Sticky con `backdrop-blur-xl` y opacidad del 70%. Debe incluir breadcrumbs en uppercase, toggle de modo claro/oscuro y perfil de usuario. 

- **Contenedor Principal**: Padding responsivo (`p-4 lg:p-6`) y ancho máximo `max-w-7xl mx-auto`. 

  

### 4. Componentes y Estética General 

- **Tarjetas (Cards)**: Usa `rounded-xl` con bordes muy finos y sombras casi imperceptibles (`shadow-sm`).  

- **Tipografía de Datos**: Usa `uppercase tracking-widest` para etiquetas y descripciones pequeñas (`text-[10px]` o `text-xs`). 

- **Gráficos**: Utiliza Recharts con áreas de gradiente lineal (Brand -> transparente), sin líneas de cuadrícula verticales y ejes minimalistas. 

- **Animaciones**: Implementa entradas suaves usando clases como `animate-in fade-in slide-in-from-bottom-2`. 

  

### 5. Comportamiento Responsive 

- Implementa un menú móvil que se active mediante un botón en el header, mostrando el sidebar como un overlay con `backdrop-blur-sm` de fondo. 

--- 