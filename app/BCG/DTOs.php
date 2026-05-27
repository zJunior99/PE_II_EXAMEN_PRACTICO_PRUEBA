<?php

final class BcgMatrixPointDto
{
    public string $productName;
    public float $prm;
    public float $tcm;
    public string $classification;
    public float $bubbleSize;
    public string $color;

    public function __construct(string $productName, float $prm, float $tcm, string $classification, float $bubbleSize, string $color)
    {
        $this->productName = $productName;
        $this->prm = $prm;
        $this->tcm = $tcm;
        $this->classification = $classification;
        $this->bubbleSize = $bubbleSize;
        $this->color = $color;
    }

    public function toArray(): array
    {
        return [
            'productName' => $this->productName,
            'prm' => $this->prm,
            'tcm' => $this->tcm,
            'classification' => $this->classification,
            'bubbleSize' => $this->bubbleSize,
            'color' => $this->color,
        ];
    }
}

final class BcgProjectStateDto
{
    public float $totalVentas;
    public string $fechaCalculo;
    public array $products;
    public array $matrix;

    public function __construct(float $totalVentas, string $fechaCalculo, array $products, array $matrix)
    {
        $this->totalVentas = $totalVentas;
        $this->fechaCalculo = $fechaCalculo;
        $this->products = $products;
        $this->matrix = $matrix;
    }

    public function toArray(): array
    {
        return [
            'totalVentas' => $this->totalVentas,
            'fechaCalculo' => $this->fechaCalculo,
            'products' => $this->products,
            'matrix' => $this->matrix,
        ];
    }
}

