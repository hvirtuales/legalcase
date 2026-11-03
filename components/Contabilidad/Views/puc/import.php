<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="mx-auto max-w-2xl">
    <div class="mb-4 flex items-center gap-2">
        <a href="<?= base_url('contabilidad/puc') ?>" class="btn btn-secondary btn-sm"><i class="icon icon-arrow-left"></i> Volver</a>
        <h5 class="font-semibold">Importar Plan Único de Cuentas desde CSV</h5>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-warning text-sm">
                <i class="icon icon-exclamation-circle"></i>
                <span>El PUC es <strong>global</strong>: la importación afecta a todas las empresas de la plataforma.</span>
            </div>

            <div class="alert alert-info">
                <i class="icon icon-info-circle"></i>
                <div>
                    <p>El archivo debe usar delimitador <strong>;</strong> con estas columnas:</p>
                    <p class="mt-1 font-mono text-xs break-all">codigo;nombre;tipo;naturaleza;nivel;codigo_padre;descripcion</p>
                    <p class="mt-1 text-xs text-muted">Tipo: activo/pasivo/patrimonio/ingreso/gasto/costo · Naturaleza: debito/credito (se infieren si se omiten).</p>
                    <p class="mt-2">
                        <a href="<?= base_url('contabilidad/puc/plantilla') ?>" class="text-primary"><i class="icon icon-download"></i> Descargar plantilla</a>
                    </p>
                </div>
            </div>

            <form action="<?= base_url('contabilidad/puc/importar') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="csv">Archivo CSV</label>
                    <input type="file" name="csv" id="csv" class="form-input" accept=".csv,text/csv" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Modo de importación</label>
                    <div class="flex flex-col gap-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="mode" value="add" checked>
                            <span><strong>Agregar</strong> — solo inserta cuentas nuevas; omite las que ya existen (por código).</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="mode" value="upsert">
                            <span><strong>Actualizar</strong> — inserta las nuevas y <em>actualiza</em> las existentes con los datos del archivo.</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <a href="<?= base_url('contabilidad/puc') ?>" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="icon icon-upload"></i> Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
