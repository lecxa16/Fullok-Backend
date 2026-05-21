# Plan de negocio — Fullok Wallet

> Documento de costos operativos y unit economics. Diseñado para incluirse en
> la presentación a inversionistas / dueños de cadenas de gasolineras.
> **Versión:** 1.0 · **Fecha:** 21 de mayo de 2026
> **Tipo de cambio asumido:** 1 USD = 18.50 MXN

---

## 1. Resumen ejecutivo

Fullok Wallet es operacionalmente eficiente porque los **dos costos
variables más grandes — facturación CFDI y procesamiento de tickets con
IA — están bajo control total**. El costo dominante es el **valor de
las recompensas canjeadas**, lo cual es por diseño: ese gasto es lo que
hace que el programa funcione.

**Bottom line a 3,000 usuarios activos/mes:**

| Concepto | Costo total mensual | Por usuario activo |
|---|---|---|
| Recompensas canjeadas | $10,773 MXN | $3.59 |
| Facturación CFDI (3,600 timbres) | $1,980 MXN | $0.66 |
| Lectura de tickets con Claude IA | $720 MXN | $0.24 |
| Infraestructura técnica (servidores, dominios, Apple/Google) | $1,110 MXN | $0.37 |
| Push notifications (Expo + APNs + FCM) | $0 MXN | $0.00 |
| **Total operativo** | **$14,583 MXN** | **$4.86 MXN/usuario/mes** |

A precios estándar de mercado para SaaS B2B retail en México, **el costo
por usuario se recupera con cualquier modelo de pricing arriba de $8–10
MXN/usuario activo o $1.50–2.00 MXN/ticket procesado**.

---

## 2. Asunciones base

Todos los cálculos parten de estos supuestos. Cambia cualquiera de ellos
y el modelo se recalcula proporcionalmente.

| Variable | Valor | Origen |
|---|---|---|
| Usuarios activos / mes | 3,000 | Escenario "early traction" — primer trimestre con marketing activo |
| Frecuencia carga / usuario / mes | 4 cargas | Promedio mercado mexicano para clientes recurrentes |
| Ticket promedio por carga | $700 MXN | Datos sectoriales 2025–2026 |
| Cargas totales / mes | 12,000 | 3,000 × 4 |
| Tasa de aprobación de tickets | 95% | Resto se rechaza por foto ilegible / duplicado |
| Tickets aprobados / mes | 11,400 | 12,000 × 0.95 |
| Earning rate | 1 punto por cada $20 MXN | Configurable desde admin |
| Puntos emitidos / mes | 399,000 | 11,400 × 35 |
| % usuarios que facturan | 30% | Solicitud del operador |
| Facturas emitidas / mes | 3,600 | 12,000 × 0.30 |
| Tasa de canje (1 - breakage) | 30% | Benchmark típico programas de lealtad — el resto vence o nunca se canjea |
| Puntos canjeados / mes | 119,700 | 399,000 × 0.30 |

> **Breakage** (puntos otorgados pero nunca canjeados) es el mayor amigo
> económico de cualquier programa de lealtad. 70% es conservador; el
> promedio industria está entre 60% y 80%.

---

## 3. Catálogo de recompensas — costo unitario al mayoreo (Monterrey, NL)

Investigación de proveedores locales en mayo 2026 (Promocionales Monterrey,
LUSA, Storyland, Trama Store, Berretto, El Conejo, Bordados Monterrey).
Cantidades mínimas asumidas: 100 piezas por SKU.

| # | Recompensa | Costo unitario (mayoreo, MXN) | Puntos requeridos | Costo por punto entregado |
|---|---|---|---|---|
| 1 | Llavero metálico grabado láser | $28 | 400 | **$0.070** |
| 2 | Banderín bordado oficial Mundial 2026 | $120 | 600 | **$0.200** |
| 3 | Gorra trucker bordada | $60 | 1,200 | **$0.050** |
| 4 | Termo acero inoxidable 600 ml | $180 | 2,000 | **$0.090** |
| 5 | Playera oficial bordada algodón | $120 | 2,500 | **$0.048** |
| 6 | Balón fútbol réplica oficial talla 5 | $130 | 3,500 | **$0.037** |
| | **Promedio ponderado** | **$106** | — | **$0.082** |

**Costo blended por punto:** ~**$0.082 MXN por punto entregado al usuario**
(ponderando catálogo asumiendo distribución equitativa de canjes).

Considerando que **solo el 30% de los puntos emitidos se canjea** (el resto
es breakage):

$$ \text{Costo real por punto emitido} = \$0.082 \times 0.30 = \$0.025 \text{ MXN/pt} $$

> Lectura: por cada punto que damos al cliente, gastamos en promedio
> **2.5 centavos**. Por cada $20 MXN de carga (= 1 punto emitido), el
> costo de recompensa es prácticamente despreciable.

---

## 4. Costo de facturación CFDI (Facturapi)

| Variable | Valor |
|---|---|
| Costo por timbre Facturapi | $0.55 MXN (rango real: $0.50–$0.60) |
| Facturas/mes (3,000 × 4 × 30%) | 3,600 |
| **Costo Facturapi mensual** | **$1,980 MXN** |
| Costo por usuario activo | $0.66 |
| Costo por ticket procesado | $0.165 |

**Nota:** Facturapi no cobra por intentos fallidos solo si la validación
del SAT pasa. Con el complemento de hidrocarburos correctamente
configurado, el rechazo debería ser <1%.

---

## 5. Costo de IA — Claude para lectura de tickets

Fullok usa **Claude Haiku 4.5** (el modelo de Anthropic optimizado para
extracción rápida y económica) para leer la foto del ticket y extraer
automáticamente: folio, monto, litros, tipo de combustible y fecha.

### Pricing Anthropic (mayo 2026)

| Modelo | Input | Output |
|---|---|---|
| Claude Haiku 4.5 | $1.00 USD / 1M tokens | $5.00 USD / 1M tokens |
| Claude Sonnet 4.7 (referencia) | $3.00 USD / 1M tokens | $15.00 USD / 1M tokens |

### Cálculo por ticket procesado

| Concepto | Tokens | Costo USD |
|---|---|---|
| Imagen del ticket (~1500×1000 px) | ~1,500 input | $0.00150 |
| Prompt de extracción (sistema + instrucciones) | ~500 input | $0.00050 |
| Respuesta JSON con datos extraídos | ~200 output | $0.00100 |
| **Total por ticket** | — | **$0.0030 USD ≈ $0.055 MXN** |

### Costo mensual a 3,000 usuarios

$$ 12{,}000 \text{ tickets} \times \$0.055 = \$660 \text{ MXN/mes} $$

> Agregando un 10% de buffer por reintentos y casos edge: **~$720 MXN/mes**.

### Beneficio neto de la IA

Sin IA, el operador tendría que tipear cada ticket a mano. A 30 segundos
por ticket × 12,000 tickets = **6,000 minutos = 100 horas/mes** de
operador. A un costo de $80 MXN/hora (operador junior MTY), eso son
**$8,000 MXN/mes en mano de obra**.

**ROI de la IA: $720 MXN gastados → $8,000 MXN ahorrados = 11× retorno.**

---

## 6. Costo de infraestructura técnica

| Concepto | Costo MXN/mes | Notas |
|---|---|---|
| Droplet DigitalOcean (4GB / 2vCPU + backups) | $640 | Suficiente para 3K–10K usuarios; subir a 8GB cuando se acerque al techo |
| Apple Developer Program | $153 | $99 USD/año amortizado |
| Google Play Developer (one-time $25 USD) | $4 | Amortizado a 60 meses |
| Dominio fullok.mx | $25 | $300/año |
| TLS / certificado | $0 | Caddy + Let's Encrypt automático |
| Expo EAS (free tier, builds preview/production) | $0 | Free para casos pequeños; upgrade a Production tier $99 USD/mes cuando escale |
| Logs / monitoreo básico | $0 | Por ahora archivos locales; agregar Sentry $26 USD/mes cuando salga a producción masiva |
| Storage S3-compatible (DO Spaces) para fotos de tickets | $185 | $10 USD/mes (250GB) |
| Email transaccional (Resend / Postmark) | $94 | Plan starter $5 USD/mes |
| **Total infraestructura** | **$1,110 MXN/mes** | |

> Esta línea es prácticamente fija. Subir de 3K a 30K usuarios solo
> sube el droplet a ~$2,500 MXN/mes y el storage proporcionalmente.

---

## 7. Push notifications

**Costo: $0.** Expo Push API es completamente gratuita incluso en
producción. Apple Push (APNs) y Firebase Cloud Messaging (FCM) tampoco
cobran por entrega. Solo se requiere mantener válidas las credenciales
(.p8 de Apple en EAS).

---

## 8. Unit economics consolidado

### Por ticket procesado

| Costo | MXN |
|---|---|
| Lectura IA | $0.055 |
| Facturación CFDI (si aplica, 30% de los casos) | $0.165 promediado |
| Recompensa proporcional (1 pt = $0.025 × 35 pts emitidos) | $0.875 |
| Infraestructura proporcional | $0.092 |
| **Total** | **$1.19 MXN/ticket** |

### Por usuario activo / mes

| Costo | MXN |
|---|---|
| Recompensas | $3.59 |
| Facturación CFDI | $0.66 |
| IA | $0.24 |
| Infraestructura | $0.37 |
| **Total operativo** | **$4.86 MXN/usuario/mes** |

### Costo por punto entregado al usuario

$$ \boxed{\$0.025 \text{ MXN por punto emitido}} $$

(Incluyendo breakage de 70%.)

---

## 9. Pricing al cliente — propuesta para gasolineras

Asumiendo que Fullok cobra al operador (cadena de gasolineras) por usar
el SaaS. Tres modelos viables:

### A) Por usuario activo
- **Precio recomendado:** $15 MXN / usuario activo / mes (3× costo)
- **Margen bruto:** $10.14 / usuario = 67%
- **Punto de equilibrio:** 1,500 usuarios cubre la operación base.

### B) Por ticket procesado
- **Precio:** $2.50 MXN / ticket aprobado
- **Margen:** $1.31 / ticket = 52%
- Mejor para operadores con flujo irregular.

### C) Mensualidad fija por estación
- **Precio:** $3,500 MXN / mes / estación (incluye hasta 1,000 usuarios)
- Excedente: $5 MXN / usuario adicional
- Más predecible para el operador, premium para Fullok.

### Recomendación
Modelo híbrido: **mensualidad base por estación + variable por sobrepaso**.
Predice los ingresos, alinea incentivos (más usuarios = ambos ganan).

---

## 10. Proyección de escalado

Cómo cambia el costo al crecer la base de usuarios:

| Usuarios activos | Tickets/mes | Costo operativo MXN | Costo por usuario |
|---|---|---|---|
| 1,000 | 4,000 | $5,750 | $5.75 |
| 3,000 (base) | 12,000 | $14,583 | $4.86 |
| 10,000 | 40,000 | $43,500 | $4.35 |
| 30,000 | 120,000 | $123,200 | $4.11 |
| 100,000 | 400,000 | $395,000 | $3.95 |

**Economía de escala real:** la infraestructura crece menos que
linealmente, y el costo de IA + Facturapi son perfectamente lineales.
Las recompensas siguen siendo el componente dominante (~75% del costo).

---

## 11. Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Breakage menor al 70% asumido | Sube el costo de recompensas linealmente | Configurar expiración de puntos a 12 meses (ya implementado) |
| Crecimiento desmedido de uso de IA por reintentos | Sube el costo de Claude | Cache de respuestas + límite de 3 intentos por ticket |
| Fraude (tickets duplicados o falsificados) | Pérdida directa de puntos otorgados | Validación de folio único en BD (ya implementado) + revisión manual aleatoria |
| Cambio de pricing de Facturapi | Impacto directo en CFDI | El modelo absorbe hasta $0.80/timbre antes de tener que ajustar pricing al cliente |
| Caída de DigitalOcean | Operación parada | Backups diarios + plan de migración a AWS si crece más allá de 50K usuarios |

---

## 12. Hojas de cálculo abiertas para ajustar

Las variables clave a sensibilizar para diferentes escenarios:

```
- usuarios_activos_mes        →  3,000   (cambiar para sim escalado)
- frecuencia_carga_mensual    →  4       (sube a 6 con marketing fuerte)
- ticket_promedio_mxn         →  700     (varía por región)
- earning_rate_pts_por_mxn    →  20      (config admin)
- tasa_facturacion            →  30%     (puede subir a 50% en B2B)
- breakage                    →  70%     (baja con UX mejor a 50%)
- costo_facturapi_por_timbre  →  $0.55   (negociar volumen)
- costo_blended_pt_recompensa →  $0.082  (mejorar al subir volumen)
- precio_sugerido_por_usuario →  $15     (alternativa: $2.5/ticket)
```

---

**Conclusión:** A escala objetivo de 3,000 usuarios activos en el primer
año, Fullok opera a **menos de $5 MXN por usuario al mes**, con la
recompensa siendo el costo dominante por diseño. Cualquier modelo de
pricing arriba de $10–15 MXN por usuario activo deja un margen bruto
saludable y financia el crecimiento.
