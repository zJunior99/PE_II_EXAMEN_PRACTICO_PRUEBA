<?php

require __DIR__ . '/../app/Core/Session.php';
require __DIR__ . '/../app/Services/SupabaseClient.php';
require __DIR__ . '/../app/Models/Persona.php';
require __DIR__ . '/../app/Models/Proyecto.php';
require __DIR__ . '/../app/Models/ProyectoMiembro.php';
require __DIR__ . '/../app/Models/Mision.php';
require __DIR__ . '/../app/Models/Vision.php';
require __DIR__ . '/../app/Models/Valor.php';
require __DIR__ . '/../app/Models/ObjetivoEstrategico.php';
require __DIR__ . '/../app/Models/ObjetivoEspecifico.php';
require __DIR__ . '/../app/Models/CadenaValor.php';
require __DIR__ . '/../app/Models/Foda.php';
require __DIR__ . '/../app/Controllers/AuthController.php';
require __DIR__ . '/../app/Controllers/ProyectoController.php';
require __DIR__ . '/../app/BCG/Entities.php';
require __DIR__ . '/../app/BCG/DTOs.php';
require __DIR__ . '/../app/BCG/Mappers.php';
require __DIR__ . '/../app/BCG/Repositories.php';
require __DIR__ . '/../app/BCG/Services.php';
require __DIR__ . '/../app/Controllers/BcgController.php';

$controller = new ProyectoController();
$bcgController = new BcgController();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (string) ($_GET['format'] ?? '') === 'json' && (string) ($_GET['bcg'] ?? '') === '1') {
    $bcgController->getStateJson();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    switch ($action) {
        case 'save_mision':
            $controller->saveMision();
            break;
        case 'save_vision':
            $controller->saveVision();
            break;
        case 'add_valor':
            $controller->addValor();
            break;
        case 'save_valores':
            $controller->saveValores();
            break;
        case 'update_valor':
            $controller->updateValor();
            break;
        case 'create_obj_est':
            $controller->createObjetivoEstrategico();
            break;
        case 'update_obj_est':
            $controller->updateObjetivoEstrategico();
            break;
        case 'delete_obj_est':
            $controller->deleteObjetivoEstrategico();
            break;
        case 'create_obj_esp':
            $controller->createObjetivoEspecifico();
            break;
        case 'update_obj_esp':
            $controller->updateObjetivoEspecifico();
            break;
        case 'delete_obj_esp':
            $controller->deleteObjetivoEspecifico();
            break;
        case 'save_cadena_valor':
            $controller->saveCadenaValor();
            break;
        case 'save_cadena_valor_batch':
            $controller->saveCadenaValorBatch();
            break;
        case 'save_foda_cadena':
            $controller->saveFodaCadena();
            break;
        case 'save_foda_bcg':
            $controller->saveFodaBcg();
            break;
        case 'update_project_name':
            $controller->updateProjectName();
            break;
        case 'invite_member':
            $controller->inviteMiembro();
            break;
        case 'remove_member':
            $controller->eliminarMiembro();
            break;

        case 'bcg_create_product':
            $bcgController->createProductJson();
            break;
        case 'bcg_update_product':
            $bcgController->updateProductJson();
            break;
        case 'bcg_delete_product':
            $bcgController->deleteProductJson();
            break;
        case 'bcg_create_competitor':
            $bcgController->createCompetitorJson();
            break;
        case 'bcg_update_competitor':
            $bcgController->updateCompetitorJson();
            break;
        case 'bcg_delete_competitor':
            $bcgController->deleteCompetitorJson();
            break;
        case 'bcg_upsert_market_period':
            $bcgController->upsertMarketPeriodJson();
            break;
        case 'bcg_delete_market_period':
            $bcgController->deleteMarketPeriodJson();
            break;
        case 'bcg_delete_market_year_batch':
            $bcgController->deleteMarketYearBatchJson();
            break;
        case 'bcg_upsert_sector_demand_period':
            $bcgController->upsertSectorDemandPeriodJson();
            break;
        case 'bcg_delete_sector_demand_period':
            $bcgController->deleteSectorDemandPeriodJson();
            break;
        case 'bcg_save_products_batch':
            $bcgController->saveProductsBatchJson();
            break;
        case 'bcg_save_market_rates_batch':
            $bcgController->saveMarketRatesBatchJson();
            break;
        case 'bcg_save_sector_demand_batch':
            $bcgController->saveSectorDemandBatchJson();
            break;
        case 'bcg_save_competitors_batch':
            $bcgController->saveCompetitorsBatchJson();
            break;
        case 'bcg_recalculate':
            $bcgController->recalculateJson();
            break;
        default:
            header('Location: proyectos.php');
            exit;
    }

    exit;
}

$controller->show();
