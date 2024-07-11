<?php
namespace src\Entity;

use src\Constant\ConstantConstant;

class LogFile extends Entity
{
    private array $lines;
    private array $tab;
    private bool $blnPitStop;
    private bool $blnTrail;
    private int $hostStartingPosition;
    private int $dnfPosition;
    private int $cptStartPosition;
    private int $cptEndPosition;
    private Event $tempEvent;
    private Game $objGame;

    public function __construct(string $fileName=null)
    {
        if ($fileName!=null) {
            $handle = fopen(PLUGIN_PATH.$fileName, 'r');
            if ($handle!==false) {
                while (!feof($handle)) {
                    $line = fgets($handle);
                    $this->lines[] = $line;
                }
                fclose($handle);
            }
        }
        $this->tab = [
            'players' => [],
        ];
        $this->blnPitStop = false;
        $this->blnTrail = false;
        $this->initGame();
    }

    private function initGame(): void
    {
        $this->objGame = new Game();
    }

    private function isLineAnEvent(string $line): bool
    {
        $patternDnf = '/(.*) est élimin/';
        $patternPneus = '/(.*) sort du virage en dérapant de {1,2}(\d+) .*pneus (.*)/';
        $patternDnf2 = '/(.*) est parti dans les graviers/';
        $patternConso = '/(.*) rétrograde(.*)endommage sa boîte de vitesse/';
        $patternConso2 = '/(.*) fait hurler(.*)endommage sa boîte de vitesse/';
        $patternFrein = '/(.*) ecrase sa pédale de frein pour ne pas avancer trop/';
        $patternAspiration = '/(.*) peut profiter de l.aspiration sur (.*)/';
        $patternLateBrake = '/(.*) freine en entrée de virage suite à l\'aspiration/';
        $patternTeteAQueue = '/(.*) fait un tête à queue en sortie de virage/';

        $bln = true;
        if (preg_match($patternDnf, $line, $matches) ||
            preg_match($patternDnf2, $line, $matches)
        ) {
            $this->objGame->addGameEvent(
                $this->objGame->getPlayerByPlayerName($matches[1]),
                new DnfEvent([$this->dnfPosition]));
            $this->dnfPosition--;
        } elseif (preg_match($patternPneus, $line, $matches)) {
            $this->objGame->addGameEvent(
                $this->objGame->getPlayerByPlayerName($matches[1]),
                new TireEvent([ConstantConstant::CST_TIRE, $matches[2]]));
        } elseif (preg_match($patternConso, $line, $matches) || preg_match($patternConso2, $line, $matches)) {
            $this->objGame->addGameEvent(
                $this->objGame->getPlayerByPlayerName($matches[1]),
                new FuelEvent([$matches[2]]));
        } elseif (preg_match($patternFrein, $line, $matches)) {
            $this->objGame->addGameEvent(
                $this->objGame->getPlayerByPlayerName($matches[1]),
                new BrakeEvent([ConstantConstant::CST_BRAKE, 1]));
        } elseif (preg_match($patternAspiration, $line, $matches)) {
            $this->tempEvent = new TrailEvent([$this->objGame->getPlayerByPlayerName($matches[2])]);
            $this->blnTrail = true;
        } elseif (preg_match($patternLateBrake, $line, $matches)) {
            $this->objGame->addGameEvent(
                $this->objGame->getPlayerByPlayerName($matches[1]),
                new BrakeEvent([ConstantConstant::CST_TRAIL, 1]));
        } elseif (preg_match($patternTeteAQueue, $line, $matches)) {
            $this->objGame->addGameEvent(
                $this->objGame->getPlayerByPlayerName($matches[1]),
                new TaqEvent());
        } else {
            $bln = false;
        }
        return $bln;
    }

    private function isLineATest(string $line): bool
    {
        $patternTestDepart = '/(.*) test Départ :(\d*)/';
        $patternTestBody = '/Test carrosserie pour (.*) : Jet = ([\d]*) {2}\(requis(.*)/';
        $patternTest = '/(.*) : Test (.*) : Jet = (\d*).*requis ([<>\d]*)/';

        $bln = true;
        if (preg_match($patternTestDepart, $line, $matches)) {
            $this->objGame->addGameTest(
                $this->objGame->getPlayerByPlayerName($matches[1]),
                new StartTest($matches[2]));
        } elseif (preg_match($patternTestBody, $line, $matches)) {
            $this->objGame->addGameTest(
                $this->objGame->getPlayerByPlayerName($matches[1]),
                new BodyTest($matches[2], $matches[3]));
        } elseif (preg_match($patternTest, $line, $matches)) {
            $this->objGame->addTest($matches);
        } else {
            $bln = false;
        }
        return $bln;
    }

    public function isAnotherLine(string $line): bool
    {
        $patternChoixStand = '/(.*) choisi son stand/';
        $patternMove = '/(.*) passe la (.*) et fait (\d*) au/';
        $patternWinner = '/(.*) remporte la course/';
        $patternFinish = '/(.*) franchit la ligne d/';
        $patternPitStop = '/(.*) s.arrête aux stands/';
        $patternFreinAnnul = '/(.*) choisit finalement de ne pas appuyer sur le frein/';

        $bln = true;
        if (preg_match($patternChoixStand, $line, $matches)) {
            $this->objGame->addPlayer($matches[1], $this->cptStartPosition);
            $this->cptStartPosition++;
            $this->dnfPosition++;
        } elseif (strpos($line, 'vous de choisir votre stand')!==false) {
            $this->objGame->addPlayer('unknown', $this->cptStartPosition);
            $this->hostStartingPosition = $this->cptStartPosition;
            $this->cptStartPosition++;
            $this->dnfPosition++;
        } elseif (preg_match($patternMove, $line, $matches)) {
            $currentPlayer = $this->objGame->getPlayerByPlayerName($matches[1]);
            if ($this->blnTrail) {
                $activePlayer = $this->objGame->getActivePlayer();
                if ($activePlayer->isEqual($currentPlayer)) {
                    $this->tempEvent->setType(ConstantConstant::CST_ACCEPTED);
                } else {
                    $this->tempEvent->setType(ConstantConstant::CST_DECLINED);
                }
                $this->objGame->addGameEvent($activePlayer, $this->tempEvent);
                $this->blnTrail = false;
                $this->tempEvent = new Event();
            }
            $this->objGame->addGear(
                $currentPlayer,
                new Gear((int)substr($matches[2], 0, 1), $matches[3]));
        } elseif (preg_match($patternWinner, $line, $matches) || preg_match($patternFinish, $line, $matches)) {
            $this->objGame->setFinalPosition(
                $this->objGame->getPlayerByPlayerName($matches[1]),
                $this->cptEndPosition);
            $this->cptEndPosition++;
        } elseif ($this->blnPitStop || preg_match($patternPitStop, $line, $matches)) {
            $this->dealWithPitStop($line);
        } elseif (preg_match($patternFreinAnnul, $line, $matches)) {
            $this->objGame->getEventCollection()->deleteLast();
            $this->objGame->getActivePlayer()->getEventCollection()->deleteLast();
        } else {
            $bln = false;
        }
        return $bln;
    }

    public function parse(): array
    {
        $this->cptStartPosition = 1;
        $this->dnfPosition = 0;
        $this->cptEndPosition = 1;
        $this->tempEvent = new Event();

        $arrLignesNonTraitees = [];
        
        foreach ($this->lines as $line) {
            if ($this->excludeLines($line)) {
                continue;
            }

            if ($this->isLineAnEvent($line)) {
                continue;
            }

            if ($this->isLineATest($line)) {
                continue;
            }

            if ($this->isAnotherLine($line)) {
                continue;
            }

            // Ligne non traitée.
            if ($line!='') {
                $arrLignesNonTraitees[] = $line;
            }
        }
        return $arrLignesNonTraitees;
    }

    private function excludeLines(string $line): bool
    {
        $blnOk = false;
        // L'idée est de chercher certaines phrases clés qui sont à ignorer.
        // Ca permet d'éviter de passer dans toutes les regexp et de ne rien trouver.
        $checks = [
            "c'est votre tour!",
            "perd un morceau",
            "perd un point moteur",
            "perd en adhérence",
            "Bienvenue dans les essais",
            "Score = Nombre de Coups",
            "Le commissaire de course",
            "En cas de comportement",
            "La piste est",
            "est parti comme une fusée",
            "Essai n°",
            "La course commencera dès le choix",
            "Faites vrombir les moteurs",
            "de pénalité pour être sorti du virage",
            "Le temps est au beau fixe",
            "Il va pleuvoir pendant toute la course.",
            "a calé !",
            "abandon par un accrochage",
            "automatiquement raté du fait de l'élimination",
        ];
        foreach ($checks as $check) {
            if (strpos($line, $check)!==false) {
                return true;
            }
        }
        return $blnOk;
    }
    
    private function dealWithPitStop(string $line): void
    {
        $patternLongStop = '/(.*) choisit un arrêt long/';
        $patternShortStopFail = '/(.*) termine son tour à enguirlander les mécanos . \(jet = (\d*) /';
        $patternShortStopSuccess = '/(.*) repart immédiatement des stands . \(jet = (\d*) /';
        if (preg_match($patternLongStop, $line, $matches)) {
            // On ajoute un arrêt long.
            $this->objGame->addGameEvent(
                $this->objGame->getPlayerByPlayerName($matches[1]),
                new PitStopEvent(true));
            $this->blnPitStop = false;
        } elseif (preg_match($patternShortStopFail, $line, $matches)) {
            // 13champion93 termine son tour à enguirlander les mécanos ! (jet = 17 , requis <10)
            $this->objGame->addGameEvent(
                $this->objGame->getPlayerByPlayerName($matches[1]),
                new PitStopEvent(false, true));
            $this->objGame->addGameTest(
                $this->objGame->getPlayerByPlayerName($matches[1]),
                new PitStopTest($matches[2]));
                $this->blnPitStop = false;
        } elseif (preg_match($patternShortStopSuccess, $line, $matches)) {
            // Antho repart immédiatement des stands ! (jet = 1 , requis <10)
            $this->objGame->addGameEvent(
                $this->objGame->getPlayerByPlayerName($matches[1]),
                new PitStopEvent(false, false));
            $this->objGame->addGameTest(
                $this->objGame->getPlayerByPlayerName($matches[1]),
                new PitStopTest($matches[2]));
                $this->objGame->setIgnoreMove();
            $this->blnPitStop = false;
        } else {
            $this->blnPitStop = true;
        }
    }
    
    public function display(): string
    {
        return $this->objGame->getController()->display();
    }
}
