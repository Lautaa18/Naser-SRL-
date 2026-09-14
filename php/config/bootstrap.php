<?php

function hasColumn(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND COLUMN_NAME = ?'
    );

    $st->execute([$table, $column]);

    return (bool)$st->fetchColumn();
}


function naser_bootstrap(PDO $pdo): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $done = true;


    /*
    |--------------------------------------------------------------------------
    | SECTORES
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sectores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            orden INT NOT NULL DEFAULT 0
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");


    /*
    |--------------------------------------------------------------------------
    | USUARIOS
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(120) NOT NULL,
            email VARCHAR(160) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,

            rol ENUM(
                'admin',
                'supervisor',
                'ventas',
                'operador',
                'usuario'
            ) NOT NULL DEFAULT 'operador',

            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");


    /*
    |--------------------------------------------------------------------------
    | PERMISOS POR SECTOR
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuario_sector (
            usuario_id INT NOT NULL,
            sector_id INT NOT NULL,

            puede_ver TINYINT(1) NOT NULL DEFAULT 1,
            puede_editar TINYINT(1) NOT NULL DEFAULT 0,

            PRIMARY KEY(usuario_id, sector_id),

            CONSTRAINT fk_us_usuario
                FOREIGN KEY(usuario_id)
                REFERENCES usuarios(id)
                ON DELETE CASCADE,

            CONSTRAINT fk_us_sector
                FOREIGN KEY(sector_id)
                REFERENCES sectores(id)
                ON DELETE CASCADE
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");


    /*
    |--------------------------------------------------------------------------
    | CARPETAS
    |--------------------------------------------------------------------------
    |
    | IMPORTANTE:
    | Acá solamente se crea la TABLA carpetas.
    | NO se crean carpetas automáticamente.
    |
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS carpetas (
            id INT AUTO_INCREMENT PRIMARY KEY,

            sector_id INT NOT NULL,

            nombre VARCHAR(160) NOT NULL,

            carpeta_padre_id INT NULL,

            orden INT NOT NULL DEFAULT 0,

            activa TINYINT(1) NOT NULL DEFAULT 1,

            CONSTRAINT fk_c_sector
                FOREIGN KEY(sector_id)
                REFERENCES sectores(id)
                ON DELETE CASCADE,

            CONSTRAINT fk_c_padre
                FOREIGN KEY(carpeta_padre_id)
                REFERENCES carpetas(id)
                ON DELETE CASCADE,

            INDEX idx_c_sector_padre (
                sector_id,
                carpeta_padre_id
            )
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");


    /*
    |--------------------------------------------------------------------------
    | DOCUMENTOS
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS documentos (
            id INT AUTO_INCREMENT PRIMARY KEY,

            sector_id INT NOT NULL,

            carpeta_id INT NULL,

            titulo VARCHAR(180) NOT NULL,

            descripcion TEXT NULL,

            tipo ENUM(
                'documentacion',
                'procedimiento',
                'formulario',
                'registro',
                'checklist',
                'certificado',
                'otro'
            ) NOT NULL DEFAULT 'documentacion',

            archivo VARCHAR(255) NULL,

            nombre_original VARCHAR(255) NULL,

            activo TINYINT(1) NOT NULL DEFAULT 1,

            fecha_actualizacion DATE NOT NULL,

            estado ENUM(
                'borrador',
                'revision',
                'aprobado',
                'obsoleto'
            ) NOT NULL DEFAULT 'aprobado',

            version VARCHAR(20) NOT NULL DEFAULT '1.0',

            fecha_vencimiento DATE NULL,

            creado_por INT NULL,

            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

            CONSTRAINT fk_d_sector
                FOREIGN KEY(sector_id)
                REFERENCES sectores(id)
                ON DELETE CASCADE,

            CONSTRAINT fk_d_carpeta
                FOREIGN KEY(carpeta_id)
                REFERENCES carpetas(id)
                ON DELETE SET NULL,

            CONSTRAINT fk_d_usuario
                FOREIGN KEY(creado_por)
                REFERENCES usuarios(id)
                ON DELETE SET NULL,

            INDEX idx_d_sector_carpeta (
                sector_id,
                carpeta_id
            ),

            INDEX idx_d_titulo (
                titulo
            )
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");


    /*
    |--------------------------------------------------------------------------
    | MIGRACIONES PARA BASES ANTERIORES
    |--------------------------------------------------------------------------
    */

    $columns = [

        'carpeta_id' =>
            'ALTER TABLE documentos
             ADD COLUMN carpeta_id INT NULL AFTER sector_id',

        'nombre_original' =>
            'ALTER TABLE documentos
             ADD COLUMN nombre_original VARCHAR(255) NULL AFTER archivo',

        'estado' =>
            "ALTER TABLE documentos
             ADD COLUMN estado
             ENUM('borrador','revision','aprobado','obsoleto')
             NOT NULL DEFAULT 'aprobado'",

        'version' =>
            "ALTER TABLE documentos
             ADD COLUMN version VARCHAR(20)
             NOT NULL DEFAULT '1.0'",

        'fecha_vencimiento' =>
            'ALTER TABLE documentos
             ADD COLUMN fecha_vencimiento DATE NULL',

        'creado_por' =>
            'ALTER TABLE documentos
             ADD COLUMN creado_por INT NULL',

        'creado_en' =>
            'ALTER TABLE documentos
             ADD COLUMN creado_en
             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
    ];


    foreach ($columns as $column => $sql) {

        if (!hasColumn($pdo, 'documentos', $column)) {

            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // La migración ya puede existir.
            }
        }
    }


    try {

        $pdo->exec("
            ALTER TABLE documentos
            MODIFY tipo ENUM(
                'documentacion',
                'procedimiento',
                'formulario',
                'registro',
                'checklist',
                'certificado',
                'otro'
            )
            NOT NULL DEFAULT 'documentacion'
        ");

    } catch (Throwable $e) {
        // Evita detener el sistema si la estructura ya está actualizada.
    }


    /*
    |--------------------------------------------------------------------------
    | VERSIONES DE DOCUMENTOS
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS documento_versiones (
            id INT AUTO_INCREMENT PRIMARY KEY,

            documento_id INT NOT NULL,

            version VARCHAR(20) NOT NULL,

            archivo VARCHAR(255) NOT NULL,

            fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

            usuario_id INT NULL,

            CONSTRAINT fk_dv_doc
                FOREIGN KEY(documento_id)
                REFERENCES documentos(id)
                ON DELETE CASCADE,

            CONSTRAINT fk_dv_user
                FOREIGN KEY(usuario_id)
                REFERENCES usuarios(id)
                ON DELETE SET NULL
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");


    /*
    |--------------------------------------------------------------------------
    | FAVORITOS
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS favoritos (
            usuario_id INT NOT NULL,

            documento_id INT NOT NULL,

            PRIMARY KEY(usuario_id, documento_id),

            CONSTRAINT fk_f_user
                FOREIGN KEY(usuario_id)
                REFERENCES usuarios(id)
                ON DELETE CASCADE,

            CONSTRAINT fk_f_doc
                FOREIGN KEY(documento_id)
                REFERENCES documentos(id)
                ON DELETE CASCADE
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");


    /*
    |--------------------------------------------------------------------------
    | OPERACIONES
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS operaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,

            sector_id INT NOT NULL,

            nombre VARCHAR(160) NOT NULL,

            descripcion TEXT NULL,

            estado ENUM(
                'activa',
                'inactiva'
            ) NOT NULL DEFAULT 'activa',

            orden INT NOT NULL DEFAULT 0,

            CONSTRAINT fk_o_sector
                FOREIGN KEY(sector_id)
                REFERENCES sectores(id)
                ON DELETE CASCADE
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");


    /*
    |--------------------------------------------------------------------------
    | ACTIVIDAD / AUDITORÍA
    |--------------------------------------------------------------------------
    */

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS actividad (
            id INT AUTO_INCREMENT PRIMARY KEY,

            usuario_id INT NULL,

            accion VARCHAR(80) NOT NULL,

            detalle VARCHAR(255) NULL,

            fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

            CONSTRAINT fk_a_user
                FOREIGN KEY(usuario_id)
                REFERENCES usuarios(id)
                ON DELETE SET NULL,

            INDEX idx_a_fecha (fecha)
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci
    ");


    /*
    |--------------------------------------------------------------------------
    | SECTORES DEL SISTEMA
    |--------------------------------------------------------------------------
    */

    $sectores = [

        ['HSEQ', 'hseq', 1],

        ['Mantenimiento', 'mantenimiento', 2],

        ['Operaciones', 'operaciones', 3],

        ['Recursos Humanos', 'rrhh', 4],

        ['Finanzas', 'finanzas', 5],

        ['Compras', 'compras', 6],

        ['Ventas', 'ventas', 7],

        ['Gerencia', 'gerencia', 8],

        ['SGI', 'sgi', 9]
    ];


    $st = $pdo->prepare("
        INSERT INTO sectores (
            nombre,
            slug,
            orden
        )
        VALUES (?, ?, ?)

        ON DUPLICATE KEY UPDATE
            nombre = VALUES(nombre),
            orden = VALUES(orden)
    ");


    foreach ($sectores as $s) {
        $st->execute($s);
    }


    /*
    |--------------------------------------------------------------------------
    | ADMINISTRADOR GENERAL
    |--------------------------------------------------------------------------
    */

    $st = $pdo->prepare("
        SELECT id
        FROM usuarios
        WHERE email = ?
        LIMIT 1
    ");

    $st->execute([
        'admin@naser.test'
    ]);


    $adminId = (int)$st->fetchColumn();


    if (!$adminId) {

        $st = $pdo->prepare("
            INSERT INTO usuarios (
                nombre,
                email,
                password,
                rol,
                activo
            )
            VALUES (?, ?, ?, ?, 1)
        ");

        $st->execute([
            'Administrador General',
            'admin@naser.test',
            password_hash(
                'Admin123!',
                PASSWORD_DEFAULT
            ),
            'admin'
        ]);

        $adminId = (int)$pdo->lastInsertId();
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN PUEDE VER Y EDITAR TODOS LOS SECTORES
    |--------------------------------------------------------------------------
    */

    $pdo->prepare("
        INSERT INTO usuario_sector (
            usuario_id,
            sector_id,
            puede_ver,
            puede_editar
        )

        SELECT
            ?,
            id,
            1,
            1

        FROM sectores

        ON DUPLICATE KEY UPDATE
            puede_ver = 1,
            puede_editar = 1
    ")->execute([
        $adminId
    ]);


    /*
    |--------------------------------------------------------------------------
    | CUENTAS DE PRUEBA
    |--------------------------------------------------------------------------
    */

    $demoUsers = [

        [
            'Supervisor HSEQ',
            'hseq@naser.test',
            'Hseq123!',
            'supervisor',
            'hseq',
            1
        ],

        [
            'Supervisor Mantenimiento',
            'mantenimiento@naser.test',
            'Mantenimiento123!',
            'supervisor',
            'mantenimiento',
            1
        ],

        [
            'Supervisor Operaciones',
            'operaciones@naser.test',
            'Operaciones123!',
            'supervisor',
            'operaciones',
            1
        ],

        [
            'Supervisor RRHH',
            'rrhh@naser.test',
            'Rrhh123!',
            'supervisor',
            'rrhh',
            1
        ],

        [
            'Supervisor Finanzas',
            'finanzas@naser.test',
            'Finanzas123!',
            'supervisor',
            'finanzas',
            1
        ],

        [
            'Supervisor Compras',
            'compras@naser.test',
            'Compras123!',
            'supervisor',
            'compras',
            1
        ],

        [
            'Supervisor Ventas',
            'ventas@naser.test',
            'Ventas123!',
            'supervisor',
            'ventas',
            1
        ],

        [
            'Supervisor SGI',
            'sgi@naser.test',
            'Sgi123!',
            'supervisor',
            'sgi',
            1
        ],

        [
            'Usuario Ventas',
            'comercial@naser.test',
            'Ventas123!',
            'ventas',
            'ventas',
            1
        ],

        [
            'Operador Prueba',
            'operador@naser.test',
            'Operador123!',
            'operador',
            'operaciones',
            0
        ]
    ];


    foreach (
        $demoUsers as [
            $name,
            $email,
            $plain,
            $role,
            $slug,
            $edit
        ]
    ) {

        $st = $pdo->prepare("
            SELECT id
            FROM usuarios
            WHERE email = ?
            LIMIT 1
        ");

        $st->execute([
            $email
        ]);


        $uid = (int)$st->fetchColumn();


        if (!$uid) {

            $st = $pdo->prepare("
                INSERT INTO usuarios (
                    nombre,
                    email,
                    password,
                    rol,
                    activo
                )
                VALUES (?, ?, ?, ?, 1)
            ");

            $st->execute([
                $name,
                $email,
                password_hash(
                    $plain,
                    PASSWORD_DEFAULT
                ),
                $role
            ]);

            $uid = (int)$pdo->lastInsertId();
        }


        $st = $pdo->prepare("
            SELECT id
            FROM sectores
            WHERE slug = ?
            LIMIT 1
        ");

        $st->execute([
            $slug
        ]);


        $sectorId = (int)$st->fetchColumn();


        if ($sectorId) {

            $pdo->prepare("
                INSERT INTO usuario_sector (
                    usuario_id,
                    sector_id,
                    puede_ver,
                    puede_editar
                )

                VALUES (
                    ?,
                    ?,
                    1,
                    ?
                )

                ON DUPLICATE KEY UPDATE
                    puede_ver = 1
            ")->execute([
                $uid,
                $sectorId,
                $edit
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SGI VISIBLE PARA TODOS
    |--------------------------------------------------------------------------
    |
    | Esto NO crea carpetas.
    | Solamente da permiso para visualizar SGI.
    |
    */

    $sgiId = (int)$pdo->query("
        SELECT id
        FROM sectores
        WHERE slug = 'sgi'
        LIMIT 1
    ")->fetchColumn();


    if ($sgiId) {

        $pdo->prepare("
            INSERT INTO usuario_sector (
                usuario_id,
                sector_id,
                puede_ver,
                puede_editar
            )

            SELECT
                u.id,
                ?,
                1,

                CASE
                    WHEN u.rol = 'admin'
                    THEN 1
                    ELSE 0
                END

            FROM usuarios u

            ON DUPLICATE KEY UPDATE
                puede_ver = 1
        ")->execute([
            $sgiId
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | IMPORTANTE - CARPETAS SGI
    |--------------------------------------------------------------------------
    |
    | NO se crean carpetas predeterminadas.
    |
    | Las carpetas del SGI se crearán solamente cuando:
    |
    | 1. El usuario las cree manualmente.
    | 2. Se cargue una carpeta completa.
    | 3. Se importe una estructura desde ZIP.
    |
    | De esta manera NASER conserva únicamente la estructura real
    | que el usuario quiera cargar.
    |
    */


    /*
    |--------------------------------------------------------------------------
    | OPERACIONES DE DEMOSTRACIÓN
    |--------------------------------------------------------------------------
    */

    if (
        (int)$pdo
            ->query(
                'SELECT COUNT(*) FROM operaciones'
            )
            ->fetchColumn() === 0
    ) {

        $ops = [

            [
                'hseq',
                'Inspección HSEQ',
                'Inspecciones de seguridad, ambiente y condiciones de trabajo.',
                1
            ],

            [
                'hseq',
                'Permiso de trabajo',
                'Gestión y control de permisos de trabajo.',
                2
            ],

            [
                'mantenimiento',
                'Mantenimiento preventivo',
                'Planificación y seguimiento preventivo de unidades y equipos.',
                1
            ],

            [
                'mantenimiento',
                'Control de unidades',
                'Seguimiento de estado y disponibilidad de unidades.',
                2
            ],

            [
                'operaciones',
                'Slickline',
                'Operaciones de Slickline y trabajos asociados.',
                1
            ],

            [
                'operaciones',
                'Well Testing',
                'Pruebas de pozo, mediciones y control operativo.',
                2
            ],

            [
                'operaciones',
                'Flow Back',
                'Operaciones de Flow Back y control de retorno.',
                3
            ],

            [
                'rrhh',
                'Inducción de personal',
                'Ingreso, formación e inducción del personal.',
                1
            ],

            [
                'finanzas',
                'Control presupuestario',
                'Seguimiento presupuestario y administrativo.',
                1
            ],

            [
                'compras',
                'Solicitud de compra',
                'Gestión de solicitudes y compras.',
                1
            ],

            [
                'ventas',
                'Seguimiento comercial',
                'Oportunidades y seguimiento comercial.',
                1
            ],

            [
                'sgi',
                'Auditoría interna SGI',
                'Seguimiento de auditorías internas.',
                1
            ]
        ];


        foreach (
            $ops as [
                $slug,
                $name,
                $desc,
                $order
            ]
        ) {

            $st = $pdo->prepare("
                SELECT id
                FROM sectores
                WHERE slug = ?
            ");

            $st->execute([
                $slug
            ]);


            $sectorId =
                (int)$st->fetchColumn();


            if ($sectorId) {

                $pdo->prepare("
                    INSERT INTO operaciones (
                        sector_id,
                        nombre,
                        descripcion,
                        estado,
                        orden
                    )

                    VALUES (
                        ?,
                        ?,
                        ?,
                        'activa',
                        ?
                    )
                ")->execute([
                    $sectorId,
                    $name,
                    $desc,
                    $order
                ]);
            }
        }
    }
}