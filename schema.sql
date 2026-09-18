CREATE TABLE IF NOT EXISTS medicos (
    id_medico INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    especialidad TEXT NOT NULL,
    centro_salud TEXT,
    lat REAL,
    lng REAL,
    sector TEXT DEFAULT 'San Miguelito',
    activo INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS productos (
    id_producto INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    categoria TEXT NOT NULL,
    material_promocional TEXT
);

CREATE TABLE IF NOT EXISTS visitas (
    id_visita INTEGER PRIMARY KEY AUTOINCREMENT,
    id_medico INTEGER NOT NULL REFERENCES medicos(id_medico),
    id_producto INTEGER NOT NULL REFERENCES productos(id_producto),
    material_entregado TEXT,
    duracion_min INTEGER,
    resultado TEXT CHECK(resultado IN ('Efectiva','Cancelada')),
    fecha TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS prescripciones (
    id_prescripcion INTEGER PRIMARY KEY AUTOINCREMENT,
    id_medico INTEGER NOT NULL REFERENCES medicos(id_medico),
    id_producto INTEGER NOT NULL REFERENCES productos(id_producto),
    cantidad_estimada REAL,
    fecha TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS plan_visitas (
    id_plan INTEGER PRIMARY KEY AUTOINCREMENT,
    id_medico INTEGER NOT NULL REFERENCES medicos(id_medico),
    mes TEXT NOT NULL,
    meta_visitas INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_visitas_medico ON visitas(id_medico);
CREATE INDEX IF NOT EXISTS idx_visitas_fecha ON visitas(fecha);
CREATE INDEX IF NOT EXISTS idx_prescripciones_medico ON prescripciones(id_medico);
CREATE INDEX IF NOT EXISTS idx_plan_medico_mes ON plan_visitas(id_medico, mes);

-- Datos semilla: productos de ginecología
INSERT OR IGNORE INTO productos (id_producto, nombre, categoria, material_promocional) VALUES
(1, 'Clotrimazol 500mg', 'Ginecología', 'Folleto + muestra'),
(2, 'Progesterona 200mg', 'Ginecología', 'Folleto'),
(3, 'Metronidazol óvulos', 'Ginecología', 'Muestra médica'),
(4, 'Ácido fólico 5mg', 'Ginecología', 'Folleto'),
(5, 'Calcio + Vit D', 'Ginecología', 'Folleto + muestra'),
(6, 'Anticonceptivo oral combo', 'Ginecología', 'Material visual');
