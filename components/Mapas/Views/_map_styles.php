<?php

/**
 * Los estilos del VISOR: el lienzo, los pines y la ficha emergente.
 *
 * Un solo archivo para las dos pantallas —el editor del panel y la página
 * pública— porque un pin que se ve de un color y un tamaño al colocarlo y de
 * otro al publicarlo no sirve para colocar nada. El editor lo incrusta igual
 * que la página pública; lo que cambia entre ellos es el mobiliario de
 * alrededor, no el mapa.
 *
 * Va en línea y no como archivo: la página pública es autosuficiente (§7.3) y
 * no puede enlazar hojas del núcleo ni de ningún CDN.
 */
?>
<style>
.mv-stage{
  position:relative; width:100%; height:100%; min-height:320px;
  overflow:hidden; background:var(--mv-bg,#eef1f5);
  touch-action:none;             /* el pellizco lo gestionamos nosotros */
  cursor:grab; user-select:none;
}
.mv-stage.is-panning{cursor:grabbing}
.mv-canvas{display:block; position:absolute; inset:0}

/* La capa de pines NO recibe eventos: así se puede arrastrar el plano aunque
   el cursor pase por encima de un pin. Cada pin los reactiva para sí. */
.mv-pins{position:absolute; inset:0; pointer-events:none}

.mv-pin{
  position:absolute; top:0; left:0; pointer-events:auto;
  width:26px; height:26px; padding:0; border:0; cursor:pointer;
  background:none; -webkit-appearance:none; appearance:none;
  /* La gota: un círculo con una esquina en punta, girado 45°. La punta queda
     abajo, que es la que señala el sitio exacto. */
  will-change:transform;
}
.mv-pin::before{
  content:""; display:block; width:100%; height:100%;
  background:var(--pin,#2563eb);
  border:2px solid #fff; border-radius:50% 50% 50% 0;
  transform:rotate(-45deg);
  box-shadow:0 2px 6px rgba(0,0,0,.35);
  transition:transform .12s ease;
}
.mv-pin::after{
  content:""; position:absolute; left:50%; top:50%;
  width:8px; height:8px; margin:-4px 0 0 -4px;
  background:#fff; border-radius:50%;
}
.mv-pin:hover::before,
.mv-pin:focus-visible::before{transform:rotate(-45deg) scale(1.18)}
.mv-pin:focus-visible{outline:none}
.mv-pin:focus-visible::before{box-shadow:0 0 0 3px rgba(255,255,255,.9),0 0 0 6px var(--pin,#2563eb)}
.mv-pin.is-dragging{z-index:5}
/* El mismo anuncio que en una forma: en el editor el pin se coge y se lleva. */
.mv-stage.is-editable .mv-pin{cursor:grab}
.mv-stage.is-editable .mv-pin.is-dragging{cursor:grabbing}

/* Colocando o recolocando un punto, los pines dejan de recibir el clic.
   Si no, el pin que ya está encima se lo traga (`stopPropagation`) y NO se
   puede poner un punto al lado de otro ni encima: justo lo que hace falta
   en el pasillo de un centro comercial, donde dos locales están pegados. */
.mv-stage.is-adding .mv-pin{pointer-events:none}
.mv-pin.is-dragging::before{transform:rotate(-45deg) scale(1.25)}
/* Un punto desactivado se ve en el editor —hay que poder reactivarlo— pero
   apagado, para no confundirlo con los que sí salen publicados.
   NO con `border-style:dashed`: sobre un cuadrado redondeado y girado 45°,
   los trazos caen en las esquinas y el pin se convierte en una flor. */
.mv-pin.is-off{opacity:.45}
.mv-pin.is-off::before{background:transparent; border-color:var(--pin,#2563eb); border-width:3px}
.mv-pin.is-off::after{background:var(--pin,#2563eb); opacity:.5}

/* --------------------------------------------------- las formas dibujadas */
/* La capa va entre el plano y los pines, y se mueve entera con una sola
   transformación que le pone el visor. Como la de pines, NO recibe eventos:
   los reactiva cada forma para sí, para que arrastrar el plano siga funcionando
   con el cursor por encima de una ruta. */
.mv-shapes{
  position:absolute; top:0; left:0; transform-origin:0 0;
  pointer-events:none; overflow:visible;
}
.mv-shape{pointer-events:auto; cursor:pointer}
/* El trazo visible no recibe el puntero: de eso se encarga la copia
   transparente y gorda que va debajo (`mv-shape-hit`), porque 4 px es un
   objetivo cruel con el dedo. Así el grosor con el que se ve y el grosor con
   el que se acierta dejan de ser el mismo número. */
.mv-shape-draw{pointer-events:none}
.mv-shape-hit{pointer-events:stroke}
/* Un área sí se pulsa por dentro: su relleno, aunque sea tenue, es lo que
   recibe el puntero. Con `fill:none` solo respondería el borde. */
.mv-shape-polygon .mv-shape-draw,
.mv-shape-rect .mv-shape-draw,
.mv-shape-circle .mv-shape-draw{pointer-events:fill}
.mv-shape-draw{transition:fill-opacity .12s ease, stroke-width .12s ease}
.mv-shape:hover .mv-shape-draw{fill-opacity:.32}
.mv-shape:focus{outline:none}
.mv-shape:focus-visible .mv-shape-draw{stroke-dasharray:none; stroke-width:6}
/* Desactivada en el editor: se ve —hay que poder reactivarla— pero apagada,
   con el mismo valor que un pin apagado, no un segundo convenio. */
.mv-shape.is-off{opacity:.45}

/* En el editor el cuerpo entero se arrastra, así que el puntero lo anuncia:
   `grab` es la señal de «esto se coge», y sin ella nadie prueba. En la página
   pública se queda en `pointer`, que es lo único que se puede hacer allí. */
.mv-stage.is-editable .mv-shape{cursor:grab}
.mv-stage.is-editable .mv-shape.is-dragging{cursor:grabbing}
/* Mientras se arrastra, el elemento se levanta un poco del plano —es la misma
   señal que da un manejador— y deja de reaccionar al `hover`, que si no
   parpadearía al pasar por debajo del puntero capturado. */
.mv-shape.is-dragging .mv-shape-draw{fill-opacity:.32; transition:none}

/* Un rótulo es el texto, colocado en su punto. No se pulsa para arrastrar el
   plano por encima de él, así que no lo estorba. */
.mv-shape-text{
  font-family:inherit; font-weight:700; paint-order:stroke;
  stroke:var(--mv-card,#fff); stroke-width:4; stroke-linejoin:round;
}

/* Un SUB-ELEMENTO en el editor: se dibuja —hay que poder moverlo y corregirle
   los vértices—, pero apagado. Así el botón de su ficha tiene un efecto que se
   ve: sin esto, en el editor la ruta ya estaba puesta y pulsar «cómo llegar»
   parecía no hacer nada. En la página pública no hace falta: allí directamente
   no está hasta que se pide. */
.mv-stage.is-editable .mv-shape.is-sub{opacity:.3}
.mv-stage.is-editable .mv-shape.is-sub.is-sub-on{opacity:1}

/* Y el que se ha pedido va resaltado en LAS DOS pantallas: entre diez líneas
   dibujadas, la que se acaba de encender tiene que distinguirse. */
.mv-shape.is-sub-on .mv-shape-draw{
  filter:drop-shadow(0 0 4px var(--pin,#2563eb));
}

/* Colocando o recolocando, las formas dejan de recibir el clic igual que los
   pines: sin esto no se puede trazar una ruta que cruce un área ya dibujada. */
.mv-stage.is-adding .mv-shape{pointer-events:none}

/* El trazo EN MARCHA: los guiones avanzan en el sentido en que se trazó la
   forma, que es lo que convierte una línea quieta en un recorrido que se lee.
   El desplazamiento de cada animación es **exactamente el largo del patrón**
   (10+7 y 1+6): con cualquier otro número el ciclo daría un salto al reiniciar.
   Por eso el patrón viaja también como clase y no solo como atributo. */
@keyframes mv-flow-dashed{to{stroke-dashoffset:-17}}
@keyframes mv-flow-dotted{to{stroke-dashoffset:-7}}
.mv-shape.is-flow.is-dashed .mv-shape-draw{animation:mv-flow-dashed 1.1s linear infinite}
.mv-shape.is-flow.is-dotted .mv-shape-draw{animation:mv-flow-dotted .7s linear infinite}
/* Cuando el navegador declara la animación pero no repinta —le pasa a Safari
   con `non-scaling-stroke`—, el visor la conduce desde JavaScript y marca el
   lienzo así. Hay que apagar la de CSS: una animación gana a un estilo en
   línea, y sin esto el valor que escribe el guion no tendría ningún efecto. */
.mv-stage.mv-flow-js .mv-shape.is-flow .mv-shape-draw{animation:none}
/* Quien pidió menos movimiento no quiere una ruta reptando por el plano.
   Es la misma regla que ya respeta el encuadre animado del visor.
   Los dos selectores se repiten ENTEROS a propósito: una media query **no sube
   la especificidad**, así que `.is-flow .mv-shape-draw` (dos clases) perdía
   contra `.is-flow.is-dashed .mv-shape-draw` (tres) y la animación seguía
   corriendo aunque la regla estuviera ahí. */
@media (prefers-reduced-motion:reduce){
  .mv-shape.is-flow.is-dashed .mv-shape-draw,
  .mv-shape.is-flow.is-dotted .mv-shape-draw{animation:none}
}

/* El destello de llegada: cierra el viaje diciendo CUÁL de los elementos de esa
   esquina era el que se buscaba. Dura menos de un segundo y se quita solo. */
@keyframes mv-arrived-shape{
  0%{stroke-width:14; opacity:.55}
  100%{stroke-width:inherit; opacity:1}
}
@keyframes mv-arrived-pin{
  0%{transform:scale(2.2); opacity:.35}
  60%{transform:scale(.9)}
  100%{transform:scale(1); opacity:1}
}
.mv-shape.is-arrived .mv-shape-draw{animation:mv-arrived-shape .7s ease-out}
/* En el pin el destello va en un pseudoelemento y NO en el botón: su
   `transform` es el que lo coloca sobre el plano, y animarlo lo mandaría de
   viaje por la pantalla. */
.mv-pin.is-arrived::before{animation:mv-arrived-pin .7s ease-out}

/* Los manejadores de vértice. Viven en la capa de PINES —no en el SVG— para
   que su tamaño sea el mismo a cualquier aumento sin recalcular nada por
   fotograma. Marcan el vértice con su centro, no con una punta. */
.mv-handle{
  position:absolute; top:0; left:0; pointer-events:auto;
  width:14px; height:14px; padding:0; cursor:grab;
  background:var(--mv-card,#fff); border:2px solid var(--mv-ink,#111827);
  border-radius:50%; box-shadow:0 1px 4px rgba(0,0,0,.35);
  -webkit-appearance:none; appearance:none; will-change:transform;
}
.mv-handle:hover{background:var(--mv-ink,#111827)}
.mv-handle:focus-visible{outline:none; box-shadow:0 0 0 3px rgba(255,255,255,.9),0 0 0 6px var(--mv-ink,#111827)}
.mv-handle.is-dragging{cursor:grabbing; background:var(--mv-ink,#111827); z-index:6}
/* Trazando, los manejadores tampoco reciben el clic: si no, uno de la forma
   anterior se tragaría el vértice que se intenta poner encima. */
.mv-stage.is-adding .mv-handle{pointer-events:none; opacity:.35}

/* El borrador: la forma mientras se traza. Lo pinta el MISMO código que la
   definitiva, así que lo único que cambia es el color y que no se pulsa. */
.mv-draft{pointer-events:none}
.mv-draft .mv-shape-draw{
  stroke:var(--mv-ink,#111827); stroke-dasharray:6 5; fill-opacity:.10;
}
.mv-draft-dot{fill:var(--mv-card,#fff); stroke:var(--mv-ink,#111827); stroke-width:2; vector-effect:non-scaling-stroke}

/* --------------------------------------------------- la ficha del punto */
/* Un solo número gobierna su tamaño. Estaba pensada para el escritorio y en
   el móvil —que es donde se usa— tapaba medio plano, así que va al 75 %; con
   el factor aparte, ajustarla otra vez es cambiar una cifra y no repasar
   catorce medidas a mano.
   Las que NO se escalan: el radio de la píldora del botón (999px ya es
   «redondo del todo») y el grosor de la sombra, que a escala se vería sucia. */
.mv-card{
  --mv-cs:.75;
  position:absolute; top:0; left:0;
  /* La coloca el visor con `transform`, anclada al PUNTO: así sigue al pin
     cuando se mueve o se amplía el mapa (ver `attachCard()`). Por eso
     `top/left` se quedan en 0 y aquí no se escribe posición. */
  will-change:transform;
  z-index:20; width:min(calc(19rem * var(--mv-cs)),calc(100% - 1.5rem));
  background:var(--mv-card,#fff); color:var(--mv-ink,#111827);
  border-radius:calc(.75rem * var(--mv-cs)); overflow:hidden;
  box-shadow:0 12px 32px rgba(0,0,0,.22),0 2px 6px rgba(0,0,0,.1);
  font-size:calc(.9rem * var(--mv-cs)); line-height:1.45;
}
.mv-card img{display:block; width:100%; height:calc(9rem * var(--mv-cs)); object-fit:cover; background:var(--mv-bg,#eef1f5)}
.mv-card-body{padding:calc(.85rem * var(--mv-cs)) calc(.95rem * var(--mv-cs))}
.mv-card h3{margin:0 0 calc(.2rem * var(--mv-cs)); font-size:calc(1rem * var(--mv-cs)); font-weight:700; line-height:1.25}
.mv-card p{margin:0; color:var(--mv-soft,#6b7280); font-size:calc(.85rem * var(--mv-cs))}
.mv-card-cat{
  display:inline-flex; align-items:center; gap:calc(.35rem * var(--mv-cs));
  margin-bottom:calc(.35rem * var(--mv-cs));
  font-size:calc(.7rem * var(--mv-cs)); font-weight:700; text-transform:uppercase; letter-spacing:.04em;
  color:var(--mv-soft,#6b7280);
}
.mv-card-cat i{
  width:calc(.6rem * var(--mv-cs)); height:calc(.6rem * var(--mv-cs));
  border-radius:50%; background:var(--pin,#2563eb); flex:none;
}
/* «Ver más» a todo el ancho y con los bordes del todo redondeados. En bloque
   y no en línea porque el editor añade debajo un segundo botón («Editar»):
   sueltos en línea quedaban descolocados según lo largo que fuera el texto. */
.mv-card-more{
  display:block; width:100%; box-sizing:border-box;
  margin-top:calc(.6rem * var(--mv-cs));
  padding:calc(.4rem * var(--mv-cs)) calc(.8rem * var(--mv-cs));
  background:var(--mv-ink,#111827); color:var(--mv-card,#fff);
  border:0; border-radius:999px;
  font:inherit; font-size:calc(.8rem * var(--mv-cs)); font-weight:600;
  text-align:center; text-decoration:none; cursor:pointer;
}
.mv-card-more + .mv-card-more{margin-top:calc(.35rem * var(--mv-cs))}
/* Los sub-elementos de la ficha: lo que un local OFRECE —su «cómo llegar»—,
   como botones y no como texto. Van en la hoja compartida porque la ficha la
   arma el visor y la usan por igual el editor y la página pública. */
.mv-card-subs{
  display:flex; flex-wrap:wrap; gap:calc(.35rem * var(--mv-cs,1));
  margin-top:calc(.5rem * var(--mv-cs,1));
}
.mv-card-sub{
  display:inline-flex; align-items:center; gap:.35em;
  padding:calc(.3rem * var(--mv-cs,1)) calc(.7rem * var(--mv-cs,1));
  font:inherit; font-size:calc(.78rem * var(--mv-cs,1)); font-weight:600; line-height:1.2;
  color:var(--pin,#2563eb); background:transparent; cursor:pointer;
  border:1.5px solid var(--pin,#2563eb); border-radius:999px;
  -webkit-appearance:none; appearance:none;
}
.mv-card-sub small{font-weight:500; opacity:.75}
.mv-card-sub:hover{background:color-mix(in srgb, var(--pin,#2563eb) 12%, transparent)}
/* Encendido: el botón es un INTERRUPTOR —vuelve a pulsarse para apagar la
   ruta—, así que tiene que verse cuál está puesta. */
.mv-card-sub.is-on{color:#fff; background:var(--pin,#2563eb)}
.mv-card-sub:focus-visible{outline:2px solid var(--pin,#2563eb); outline-offset:2px}

.mv-card-close{
  position:absolute; top:calc(.4rem * var(--mv-cs)); right:calc(.4rem * var(--mv-cs));
  width:calc(1.75rem * var(--mv-cs)); height:calc(1.75rem * var(--mv-cs));
  display:flex; align-items:center; justify-content:center;
  background:rgba(0,0,0,.55); color:#fff; border:0; border-radius:50%;
  cursor:pointer; font-size:calc(1rem * var(--mv-cs)); line-height:1;
}
/* -------------------------------------------------------- los controles */
.mv-tools{
  position:absolute; right:.75rem; bottom:.75rem; z-index:15;
  display:flex; flex-direction:column; gap:.35rem;
}
.mv-tools button{
  width:2.25rem; height:2.25rem; display:flex; align-items:center; justify-content:center;
  background:var(--mv-card,#fff); color:var(--mv-ink,#111827);
  border:1px solid rgba(0,0,0,.12); border-radius:.5rem; cursor:pointer;
  font-size:1rem; line-height:1; box-shadow:0 1px 3px rgba(0,0,0,.12);
}
.mv-tools button:hover{background:var(--mv-bg,#eef1f5)}
</style>
