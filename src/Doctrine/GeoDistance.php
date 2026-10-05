<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/** MariaDB Haversine distance in kilometres, clamped for floating point roundoff. */
final class GeoDistance extends FunctionNode
{
    private array $arguments = [];

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        for ($i = 0; $i < 4; ++$i) {
            if ($i > 0) {
                $parser->match(TokenType::T_COMMA);
            }
            $this->arguments[] = $parser->ArithmeticPrimary();
        }
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        // Dispatch each occurrence in SQL order so Doctrine registers repeated bound parameters.
        $latDelta = $this->arguments[0]->dispatch($sqlWalker).' - '.$this->arguments[2]->dispatch($sqlWalker);
        $originCos = $this->arguments[2]->dispatch($sqlWalker);
        $latCos = $this->arguments[0]->dispatch($sqlWalker);
        $lngDelta = $this->arguments[1]->dispatch($sqlWalker).' - '.$this->arguments[3]->dispatch($sqlWalker);

        return "(12742.0176 * ASIN(SQRT(LEAST(1, GREATEST(0, POWER(SIN(RADIANS($latDelta) / 2), 2) + COS(RADIANS($originCos)) * COS(RADIANS($latCos)) * POWER(SIN(RADIANS($lngDelta) / 2), 2))))))";
    }
}
