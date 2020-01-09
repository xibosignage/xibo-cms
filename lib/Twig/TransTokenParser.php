<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */


namespace Xibo\Twig;


use Twig\Node\Node;
use Twig\Token;

class TransTokenParser extends \Twig\TokenParser\AbstractTokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Token $token A Twig_Token instance
     *
     * @return TransNode A Twig_Node instance
     * @throws \Twig\Error\SyntaxError
     */
    public function parse(Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $count = null;
        $plural = null;
        $notes = null;

        if (!$stream->test(Token::BLOCK_END_TYPE)) {
            $body = $this->parser->getExpressionParser()->parseExpression();
        } else {
            $stream->expect(Token::BLOCK_END_TYPE);
            $body = $this->parser->subparse([$this, 'decideForFork']);
            $next = $stream->next()->getValue();
            if ('plural' === $next) {
                $count = $this->parser->getExpressionParser()->parseExpression();
                $stream->expect(Token::BLOCK_END_TYPE);
                $plural = $this->parser->subparse([$this, 'decideForFork']);
                if ('notes' === $stream->next()->getValue()) {
                    $stream->expect(Token::BLOCK_END_TYPE);
                    $notes = $this->parser->subparse([$this, 'decideForEnd'], true);
                }
            } elseif ('notes' === $next) {
                $stream->expect(Token::BLOCK_END_TYPE);
                $notes = $this->parser->subparse([$this, 'decideForEnd'], true);
            }
        }

        $stream->expect(Token::BLOCK_END_TYPE);
        $this->checkTransString($body, $lineno);

        return new TransNode($body, $plural, $count, $notes, $lineno, $this->getTag());
    }

    public function
    decideForFork(Token $token)
    {
        return $token->test(array('plural', 'notes', 'endtrans'));
    }

    public function decideForEnd(Token $token)
    {
        return $token->test('endtrans');
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @param string The tag name
     *
     * @return string
     */
    public function getTag()
    {
        return 'trans';
    }

    /**
     * @param Node $body
     * @param $lineno
     * @throws \Twig\Error\SyntaxError
     */
    protected function checkTransString(Node $body, $lineno)
    {
        foreach ($body as $i => $node) {
            if (
                $node instanceof \Twig\Node\TextNode
                ||
                ($node instanceof \Twig\Node\PrintNode && $node->getNode('expr') instanceof \Twig\Node\Expression\NameExpression)
            ) {
                continue;
            }

            throw new \Twig\Error\SyntaxError(sprintf('The text to be translated with "trans" can only contain references to simple variables'), $lineno);
        }
    }
}