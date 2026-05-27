<?php

final class BcgProductEntity
{
    public int $idProductoBcg;
    public int $idProyecto;
    public string $nombre;
    public float $ventasEmpresa;
    public float $porcentajeVentas;
    public float $tcm;
    public float $prm;
    public string $clasificacion;
    public ?string $createdAt;
    public ?string $updatedAt;

    public array $marketPeriods = [];
    public array $competitors = [];
    public array $sectorDemandPeriods = [];

    public function __construct(
        int $idProductoBcg,
        int $idProyecto,
        string $nombre,
        float $ventasEmpresa,
        float $porcentajeVentas,
        float $tcm,
        float $prm,
        string $clasificacion,
        ?string $createdAt,
        ?string $updatedAt
    ) {
        $this->idProductoBcg = $idProductoBcg;
        $this->idProyecto = $idProyecto;
        $this->nombre = $nombre;
        $this->ventasEmpresa = $ventasEmpresa;
        $this->porcentajeVentas = $porcentajeVentas;
        $this->tcm = $tcm;
        $this->prm = $prm;
        $this->clasificacion = $clasificacion;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }
}

final class BcgMarketPeriodEntity
{
    public int $idPeriodo;
    public int $idProductoBcg;
    public int $anio;
    public float $demandaMercado;

    public function __construct(int $idPeriodo, int $idProductoBcg, int $anio, float $demandaMercado)
    {
        $this->idPeriodo = $idPeriodo;
        $this->idProductoBcg = $idProductoBcg;
        $this->anio = $anio;
        $this->demandaMercado = $demandaMercado;
    }
}

final class BcgSectorDemandPeriodEntity
{
    public int $idPeriodoSector;
    public int $idProductoBcg;
    public int $anio;
    public float $demandaSector;

    public function __construct(int $idPeriodoSector, int $idProductoBcg, int $anio, float $demandaSector)
    {
        $this->idPeriodoSector = $idPeriodoSector;
        $this->idProductoBcg = $idProductoBcg;
        $this->anio = $anio;
        $this->demandaSector = $demandaSector;
    }
}

final class BcgCompetitorEntity
{
    public int $idCompetidor;
    public int $idProductoBcg;
    public string $nombre;
    public float $ventas;

    public function __construct(int $idCompetidor, int $idProductoBcg, string $nombre, float $ventas)
    {
        $this->idCompetidor = $idCompetidor;
        $this->idProductoBcg = $idProductoBcg;
        $this->nombre = $nombre;
        $this->ventas = $ventas;
    }
}

final class BcgResultEntity
{
    public int $idResultado;
    public int $idProyecto;
    public float $totalVentas;
    public string $fechaCalculo;

    public function __construct(int $idResultado, int $idProyecto, float $totalVentas, string $fechaCalculo)
    {
        $this->idResultado = $idResultado;
        $this->idProyecto = $idProyecto;
        $this->totalVentas = $totalVentas;
        $this->fechaCalculo = $fechaCalculo;
    }
}
