<?php
namespace src\Controller;

use src\Constant\ConstantConstant;
use src\Constant\LabelConstant;
use src\Constant\TemplateConstant;
use src\Entity\TireEvent;
use src\Entity\Game;
use src\Entity\Player;

class TireController extends GameController
{

    public static function displayTires(Game $objGame): string
    {
        $controller = new TireController($objGame);

        $tireEventCollection = $objGame->getEventCollection()->getClassEvent(TireEvent::class);

        $attributes = [
            LabelConstant::LBL_TIRE,
            // class additionnelle pour card-body
            'p-0',
            $controller->getRow([
                LabelConstant::LBL_CURVE_EXIT,
                LabelConstant::LBL_QUANTITY,
                LabelConstant::LBL_BLOCKED,
                LabelConstant::LBL_QUANTITY],
                false
            ),
            $controller->getRow([
                $tireEventCollection->filter([ConstantConstant::CST_TYPE=>ConstantConstant::CST_TIRE])->length(),
                $tireEventCollection->filter([ConstantConstant::CST_TYPE=>ConstantConstant::CST_TIRE])->sum(),
                $tireEventCollection->filter([ConstantConstant::CST_TYPE=>ConstantConstant::CST_BLOCKED])->length(),
                $tireEventCollection->filter([ConstantConstant::CST_TYPE=>ConstantConstant::CST_BLOCKED])->sum(),
            ])
        ];
        return $controller->getRender(TemplateConstant::TPL_CARD_SIMPLE_TABLE, $attributes);
    }

    public static function displayPlayerTires(Player $objPlayer): string
    {
        $tireEventCollection = $objPlayer->getEventCollection()->getClassEvent(TireEvent::class);

        return '<br>Pneus : <span class="badge bg-info">'
            .$tireEventCollection->filter([ConstantConstant::CST_TYPE=>ConstantConstant::CST_TIRE])->length()
            .' Sorties</span> - <span class="badge bg-danger">'
            .$tireEventCollection->filter([ConstantConstant::CST_TYPE=>ConstantConstant::CST_TIRE])->sum()
            .' Consommés</span> - <span class="badge bg-info">'
            .$tireEventCollection->filter([ConstantConstant::CST_TYPE=>ConstantConstant::CST_BLOCKED])->length()
            .' Blocages</span> - <span class="badge bg-danger">'
            .$tireEventCollection->filter([ConstantConstant::CST_TYPE=>ConstantConstant::CST_BLOCKED])->sum()
            .' Consommés</span>';
    }
    
}
