<?php

final class BcgProductMapper
{
    public static function fromRow(array $row): BcgProductEntity
    {
        return new BcgProductEntity(
            (int) ($row['id_producto_bcg'] ?? 0),
            (int) ($row['id_proyecto'] ?? 0),
            (string) ($row['nombre'] ?? ''),
            (float) ($row['ventas_empresa'] ?? 0),
            (float) ($row['porcentaje_ventas'] ?? 0),
            (float) ($row['tcm'] ?? 0),
            (float) ($row['prm'] ?? 0),
            (string) (($row['clasificacion'] ?? '') ?: 'PERRO'),
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }

    public static function toComputedUpsertRow(BcgProductEntity $p): array
    {
        return [
            'id_producto_bcg' => $p->idProductoBcg,
            'porcentaje_ventas' => $p->porcentajeVentas,
            'tcm' => $p->tcm,
            'prm' => $p->prm,
            'clasificacion' => $p->clasificacion,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    public static function toStateArray(BcgProductEntity $p): array
    {
        $periods = [];
        foreach ($p->marketPeriods as $mp) {
            if (!$mp instanceof BcgMarketPeriodEntity) {
                continue;
            }
            $periods[] = [
                'id_periodo' => $mp->idPeriodo,
                'anio' => $mp->anio,
                'demanda_mercado' => $mp->demandaMercado,
            ];
        }

        $sectorPeriods = [];
        foreach ($p->sectorDemandPeriods as $sp) {
            if (!$sp instanceof BcgSectorDemandPeriodEntity) {
                continue;
            }
            $sectorPeriods[] = [
                'id_periodo_sector' => $sp->idPeriodoSector,
                'anio' => $sp->anio,
                'demanda_sector' => $sp->demandaSector,
            ];
        }

        $competitors = [];
        foreach ($p->competitors as $c) {
            if (!$c instanceof BcgCompetitorEntity) {
                continue;
            }
            $competitors[] = [
                'id_competidor' => $c->idCompetidor,
                'nombre' => $c->nombre,
                'ventas' => $c->ventas,
            ];
        }

        return [
            'id_producto_bcg' => $p->idProductoBcg,
            'id_proyecto' => $p->idProyecto,
            'nombre' => $p->nombre,
            'ventas_empresa' => $p->ventasEmpresa,
            'porcentaje_ventas' => $p->porcentajeVentas,
            'porcentaje_ventas_pct' => ((float) $p->porcentajeVentas) * 100.0,
            'tcm' => $p->tcm,
            'prm' => $p->prm,
            'clasificacion' => $p->clasificacion,
            'bubble_size' => ((float) $p->porcentajeVentas) * 100.0,
            'market_periods' => $periods,
            'sector_demand_periods' => $sectorPeriods,
            'competitors' => $competitors,
        ];
    }
}

final class BcgMarketPeriodMapper
{
    public static function fromRow(array $row): BcgMarketPeriodEntity
    {
        return new BcgMarketPeriodEntity(
            (int) ($row['id_periodo'] ?? 0),
            (int) ($row['id_producto_bcg'] ?? 0),
            (int) ($row['anio'] ?? 0),
            (float) ($row['demanda_mercado'] ?? 0)
        );
    }
}

final class BcgSectorDemandPeriodMapper
{
    public static function fromRow(array $row): BcgSectorDemandPeriodEntity
    {
        return new BcgSectorDemandPeriodEntity(
            (int) ($row['id_periodo_sector'] ?? 0),
            (int) ($row['id_producto_bcg'] ?? 0),
            (int) ($row['anio'] ?? 0),
            (float) ($row['demanda_sector'] ?? 0)
        );
    }
}

final class BcgCompetitorMapper
{
    public static function fromRow(array $row): BcgCompetitorEntity
    {
        return new BcgCompetitorEntity(
            (int) ($row['id_competidor'] ?? 0),
            (int) ($row['id_producto_bcg'] ?? 0),
            (string) ($row['nombre'] ?? ''),
            (float) ($row['ventas'] ?? 0)
        );
    }
}

final class BcgResultMapper
{
    public static function fromRow(array $row): BcgResultEntity
    {
        return new BcgResultEntity(
            (int) ($row['id_resultado'] ?? 0),
            (int) ($row['id_proyecto'] ?? 0),
            (float) ($row['total_ventas'] ?? 0),
            (string) ($row['fecha_calculo'] ?? gmdate('c'))
        );
    }
}
