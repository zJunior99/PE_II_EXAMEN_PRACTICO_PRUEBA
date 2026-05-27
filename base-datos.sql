CREATE TABLE persona (
    id_persona SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL
);

CREATE TABLE proyecto (
    id_proyecto SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    creador_id INT NOT NULL,

    CONSTRAINT fk_proyecto_creador
        FOREIGN KEY (creador_id)
        REFERENCES persona(id_persona)
);

CREATE TABLE proyecto_miembro (
    id SERIAL PRIMARY KEY,
    id_proyecto INT NOT NULL,
    id_persona INT NOT NULL,
    rol VARCHAR(50),

    CONSTRAINT fk_pm_proyecto
        FOREIGN KEY (id_proyecto)
        REFERENCES proyecto(id_proyecto)
        ON DELETE CASCADE,

    CONSTRAINT fk_pm_persona
        FOREIGN KEY (id_persona)
        REFERENCES persona(id_persona)
        ON DELETE CASCADE,

    CONSTRAINT uq_pm_proyecto_persona
        UNIQUE (id_proyecto, id_persona)
);

CREATE TABLE vision (
    id_vision SERIAL PRIMARY KEY,
    id_proyecto INT UNIQUE NOT NULL,
    descripcion TEXT NOT NULL,

    CONSTRAINT fk_vision_proyecto
        FOREIGN KEY (id_proyecto)
        REFERENCES proyecto(id_proyecto)
        ON DELETE CASCADE
);

CREATE TABLE mision (
    id_mision SERIAL PRIMARY KEY,
    id_proyecto INT UNIQUE NOT NULL,
    descripcion TEXT NOT NULL,

    CONSTRAINT fk_mision_proyecto
        FOREIGN KEY (id_proyecto)
        REFERENCES proyecto(id_proyecto)
        ON DELETE CASCADE
);

CREATE TABLE valor (
    id_valor SERIAL PRIMARY KEY,
    id_proyecto INT NOT NULL,
    descripcion TEXT NOT NULL,

    CONSTRAINT fk_valor_proyecto
        FOREIGN KEY (id_proyecto)
        REFERENCES proyecto(id_proyecto)
        ON DELETE CASCADE
);

CREATE TABLE objetivo_estrategico (
    id_objetivo_est SERIAL PRIMARY KEY,
    id_proyecto INT NOT NULL,
    descripcion TEXT NOT NULL,

    CONSTRAINT fk_obj_est_proyecto
        FOREIGN KEY (id_proyecto)
        REFERENCES proyecto(id_proyecto)
        ON DELETE CASCADE
);

CREATE TABLE objetivo_especifico (
    id_objetivo_esp SERIAL PRIMARY KEY,
    id_objetivo_est INT NOT NULL,
    descripcion TEXT NOT NULL,

    CONSTRAINT fk_obj_esp_obj_est
        FOREIGN KEY (id_objetivo_est)
        REFERENCES objetivo_estrategico(id_objetivo_est)
        ON DELETE CASCADE
);

CREATE TABLE cadena_valor_pregunta (
    id_pregunta SERIAL PRIMARY KEY,
    numero INT UNIQUE NOT NULL,
    texto TEXT NOT NULL
);

CREATE TABLE cadena_valor_respuesta (
    id_respuesta SERIAL PRIMARY KEY,
    id_proyecto INT NOT NULL,
    id_pregunta INT NOT NULL,
    valor INT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),

    CONSTRAINT chk_cvi_valor
        CHECK (valor >= 0 AND valor <= 4),

    CONSTRAINT uq_cvi_proyecto_pregunta
        UNIQUE (id_proyecto, id_pregunta),

    CONSTRAINT fk_cvi_resp_proyecto
        FOREIGN KEY (id_proyecto)
        REFERENCES proyecto(id_proyecto)
        ON DELETE CASCADE,

    CONSTRAINT fk_cvi_resp_pregunta
        FOREIGN KEY (id_pregunta)
        REFERENCES cadena_valor_pregunta(id_pregunta)
        ON DELETE CASCADE
);

CREATE TABLE cadena_valor_resultado (
    id_proyecto INT PRIMARY KEY,
    suma INT NOT NULL,
    potencial NUMERIC(6,4) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_cvi_res_proyecto
        FOREIGN KEY (id_proyecto)
        REFERENCES proyecto(id_proyecto)
        ON DELETE CASCADE
);

CREATE TABLE foda_item (
    id_item SERIAL PRIMARY KEY,
    id_proyecto INT NOT NULL,
    fuente VARCHAR(50) NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    posicion INT NOT NULL,
    descripcion TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),

    CONSTRAINT chk_foda_tipo
        CHECK (tipo IN ('FORTALEZA', 'DEBILIDAD')),

    CONSTRAINT uq_foda_proyecto_fuente_tipo_pos
        UNIQUE (id_proyecto, fuente, tipo, posicion),

    CONSTRAINT fk_foda_proyecto
        FOREIGN KEY (id_proyecto)
        REFERENCES proyecto(id_proyecto)
        ON DELETE CASCADE
);

CREATE TABLE bcg_producto (
    id_producto_bcg SERIAL PRIMARY KEY,
    id_proyecto INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    ventas_empresa NUMERIC(14,2) NOT NULL DEFAULT 0,
    porcentaje_ventas NUMERIC(12,6) NOT NULL DEFAULT 0,
    tcm NUMERIC(12,6) NOT NULL DEFAULT 0,
    prm NUMERIC(12,6) NOT NULL DEFAULT 0,
    clasificacion VARCHAR(20) NOT NULL DEFAULT 'PERRO',
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_bcg_producto_proyecto
        FOREIGN KEY (id_proyecto)
        REFERENCES proyecto(id_proyecto)
        ON DELETE CASCADE,

    CONSTRAINT uq_bcg_producto_proyecto_nombre
        UNIQUE (id_proyecto, nombre),

    CONSTRAINT chk_bcg_producto_ventas
        CHECK (ventas_empresa >= 0)
);

CREATE TABLE bcg_mercado_periodo (
    id_periodo SERIAL PRIMARY KEY,
    id_producto_bcg INT NOT NULL,
    anio INT NOT NULL,
    demanda_mercado NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_bcg_periodo_producto
        FOREIGN KEY (id_producto_bcg)
        REFERENCES bcg_producto(id_producto_bcg)
        ON DELETE CASCADE,

    CONSTRAINT uq_bcg_periodo_producto_anio
        UNIQUE (id_producto_bcg, anio),

    CONSTRAINT chk_bcg_periodo_demanda
        CHECK (demanda_mercado >= 0)
);

CREATE TABLE bcg_demanda_sector_periodo (
    id_periodo_sector SERIAL PRIMARY KEY,
    id_producto_bcg INT NOT NULL,
    anio INT NOT NULL,
    demanda_sector NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_bcg_sector_producto
        FOREIGN KEY (id_producto_bcg)
        REFERENCES bcg_producto(id_producto_bcg)
        ON DELETE CASCADE,

    CONSTRAINT uq_bcg_sector_producto_anio
        UNIQUE (id_producto_bcg, anio),

    CONSTRAINT chk_bcg_sector_demanda
        CHECK (demanda_sector >= 0)
);

CREATE TABLE bcg_competidor (
    id_competidor SERIAL PRIMARY KEY,
    id_producto_bcg INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    ventas NUMERIC(14,2) NOT NULL DEFAULT 0,

    CONSTRAINT fk_bcg_competidor_producto
        FOREIGN KEY (id_producto_bcg)
        REFERENCES bcg_producto(id_producto_bcg)
        ON DELETE CASCADE,

    CONSTRAINT uq_bcg_competidor_producto_nombre
        UNIQUE (id_producto_bcg, nombre),

    CONSTRAINT chk_bcg_competidor_ventas
        CHECK (ventas >= 0)
);

CREATE TABLE bcg_resultado (
    id_resultado SERIAL PRIMARY KEY,
    id_proyecto INT NOT NULL,
    total_ventas NUMERIC(14,2) NOT NULL DEFAULT 0,
    fecha_calculo TIMESTAMP NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_bcg_resultado_proyecto
        FOREIGN KEY (id_proyecto)
        REFERENCES proyecto(id_proyecto)
        ON DELETE CASCADE,

    CONSTRAINT chk_bcg_resultado_total
        CHECK (total_ventas >= 0)
);

CREATE INDEX idx_bcg_producto_proyecto ON bcg_producto(id_proyecto);
CREATE INDEX idx_bcg_periodo_producto ON bcg_mercado_periodo(id_producto_bcg);
CREATE INDEX idx_bcg_sector_producto ON bcg_demanda_sector_periodo(id_producto_bcg);
CREATE INDEX idx_bcg_competidor_producto ON bcg_competidor(id_producto_bcg);
CREATE INDEX idx_bcg_resultado_proyecto ON bcg_resultado(id_proyecto);

INSERT INTO cadena_valor_pregunta (numero, texto) VALUES
(1, 'La empresa tiene una política sistematizada de cero defectos en la producción de productos/servicios.'),
(2, 'La empresa emplea los medios productivos tecnológicamente más avanzados de su sector.'),
(3, 'La empresa dispone de un sistema de información y control de gestión eficiente y eficaz.'),
(4, 'Los medios técnicos y tecnológicos de la empresa están preparados para competir en un futuro a corto, medio y largo plazo.'),
(5, 'La empresa es un referente en su sector en I+D+i.'),
(6, 'La excelencia de los procedimientos de la empresa (ISO, etc.) son una principal fuente de ventaja competitiva.'),
(7, 'La empresa dispone de página web, y esta se emplea no sólo como escaparate virtual de productos/servicios, sino también para establecer relaciones con clientes y proveedores.'),
(8, 'Los productos/servicios que desarrolla nuestra empresa llevan incorporada una tecnología difícil de imitar.'),
(9, 'La empresa es referente en su sector en la optimización, en términos de coste, de su cadena de producción, siendo ésta una de sus principales ventajas competitivas.'),
(10, 'La informatización de la empresa es una fuente de ventaja competitiva clara respecto a sus competidores.'),
(11, 'Los canales de distribución de la empresa son una importante fuente de ventajas competitivas.'),
(12, 'Los productos/servicios de la empresa son altamente y diferencialmente valorados por el cliente respecto a nuestros competidores.'),
(13, 'La empresa dispone y ejecuta un sistemático plan de marketing y ventas.'),
(14, 'La empresa tiene optimizada su gestión financiera.'),
(15, 'La empresa busca continuamente mejorar la relación con sus clientes cortando los plazos de ejecución, personalizando la oferta o mejorando las condiciones de entrega, siempre partiendo de un plan previo.'),
(16, 'La empresa es referente en su sector en el lanzamiento de innovadores productos y servicios de éxito demostrado en el mercado.'),
(17, 'Los Recursos Humanos son especialmente responsables del éxito de la empresa, considerándolos incluso como el principal activo estratégico.'),
(18, 'Se tiene una plantilla altamente motivada, que conoce con claridad las metas, objetivos y estrategias de la organización.'),
(19, 'La empresa siempre trabaja conforme a una estrategia y objetivos claros.'),
(20, 'La gestión del circulante está optimizada.'),
(21, 'Se tiene definido claramente el posicionamiento estratégico de todos los productos de la empresa.'),
(22, 'Se dispone de una política de marca basada en la reputación que la empresa genera, en la gestión de relación con el cliente y en el posicionamiento estratégico previamente definido.'),
(23, 'La cartera de clientes de nuestra empresa está altamente fidelizada, ya que tenemos como principal propósito deleitarlos día a día.'),
(24, 'Nuestra política y equipo de ventas y marketing es una importante ventaja competitiva de nuestra empresa respecto al sector.'),
(25, 'El servicio al cliente que prestamos es una de nuestras principales ventajas competitivas respecto a nuestros competidores.');
