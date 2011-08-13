<?php

class CalcLexer
{
    public function __construct($code) {
        $this->code = $code;
        $this->code_end = strlen($code);
        $this->curr_pos = 0;
        $this->curr_tok = CalcToken::EOF;
        $this->curr_val = '';
    }

    private $code;
    private $code_end;
    private $curr_pos;
    private $curr_tok;
    private $curr_val;

    public function CurrentToken() {
        return $this->curr_tok;
    }

    public function CurrentValue() {
        return $this->curr_val;
    }

    public function NextToken() {
        if ($this->curr_pos == $this->code_end) {
            $this->curr_tok = CalcToken::EOF;
            return;
        }

        // ignore white space
        while (true) {
            $c = $this->code[$this->curr_pos];

            if ($c != " " && $c != "\t" && $c != "\n")
                break;
            
            $this->curr_pos += 1;

            if ($this->curr_pos == $this->code_end) {
                $this->curr_tok = CalcToken::EOF;
                return;
            }
        }

        switch ($c) {
            case '+': $this->curr_tok = CalcToken::ADD; $this->curr_pos += 1; return;
            case '-': $this->curr_tok = CalcToken::SUB; $this->curr_pos += 1; return;
            case '*': $this->curr_tok = CalcToken::MUL; $this->curr_pos += 1; return;
            case '/': $this->curr_tok = CalcToken::DIV; $this->curr_pos += 1; return;
            case '(': $this->curr_tok = CalcToken::LBK; $this->curr_pos += 1; return;
            case ')': $this->curr_tok = CalcToken::RBK; $this->curr_pos += 1; return;
        }

        // (\.[0-9]+) | ([0-9]+(\.[0-9]*)?)
        if ($this->IsNum($c) || ($c == '.' && $this->curr_pos < $this->code_end - 1 && $this->IsNum($this->code[$this->curr_pos + 1]))) {
            $num_begin = $this->curr_pos;
            $num_length = 1;

            $has_dot = $c == '.';

            while (true) {
                $this->curr_pos += 1;

                if ($this->curr_pos == $this->code_end)
                    break;

                $c = $this->code[$this->curr_pos];

                if (!$this->IsNum($c)) {
                    if ($has_dot)
                        break;
                
                    if ($c == '.')
                        $has_dot = true;
                    else
                        break;
                }

                $num_length += 1;
            }

            $this->curr_val = substr($this->code, $num_begin, $num_length);
            $this->curr_tok = CalcToken::NUM;

            return;
        }

        $this->curr_tok = CalcToken::ERR;
    }

    private function IsNum($c) {
        return $c == '0' || $c == '1' || $c == '2' || 
        $c == '3' || $c == '4' || $c == '5' || 
        $c == '6' || $c == '7' || $c == '8' ||
        $c == '9';
    }
}

class CalcParser
{
    public function __construct($code) {
        $this->lexer = new CalcLexer($code);
    }

    private $lexer;

    private function NextToken() {
        $this->lexer->NextToken();
    }

    private function CurrentToken() {
        return $this->lexer->CurrentToken();
    }

    private function CurrentValue() {
        return $this->lexer->CurrentValue();
    }

    public function Run() {
        return $this->ParseExp(0);
    }

    public function ParseExp($pri) {
        $lh = $this->ParseUnary();

        return $this->ParseRightSide($pri, $lh);
    }

    public function ParseUnary() {
        $this->NextToken();

        if ($this->CurrentToken() == CalcToken::LBK) {
            $ast = $this->ParseExp(0);

            if ($this->CurrentToken() != CalcToken::RBK) {
                $this->Error("Missing ')'");
            }

            $this->NextToken();

            return $ast;
        } else if ($this->CurrentToken() == CalcToken::NUM) {
            $ast = new CalcNumAST($this->CurrentValue());
            
            $this->NextToken();

            return $ast;
        }
    }

    public function ParseRightSide($pri, $lh) {
        while(true) {
            if (!$this->IsBinOp($this->CurrentToken())) {
                return $lh;
            }

            $pri2 = $this->CurrentToken();

            if ($pri >= $pri2)
                return $lh;

            $rh = $this->ParseExp($pri2);
        
            $lh = new CalcBinOpAST($pri2, $lh, $rh);
        }
    }

    private function IsBinOp($token) {
        return CalcToken::ADD <= $token && $token <= CalcToken::DIV;
    }

    private function Error($msg) {
        throw new Exception($msg);
    }
}

class CalcToken {
    const ERR = 0;
    const EOF = 1;
    const NUM = 2;

    const LBK = 10;
    const RBK = 11;

    const ADD = 20;
    const SUB = 21;
    const MUL = 22;
    const DIV = 23;
}

class CalcNumAST {
    public function __construct($value) {
        $this->value = $value;
    }

    private $value;

    public function Exec() {
        return $this->value;
    }
}

class CalcBinOpAST {
    public function __construct($op, $lh, $rh) {
        $this->op = $op;
        $this->lh = $lh;
        $this->rh = $rh;
    }

    private $op;
    private $lh;
    private $rh;

    public function Exec() {
        $lv = $this->lh->Exec();
        $rv = $this->rh->Exec();

        switch ($this->op) {
            case CalcToken::ADD : return $lv + $rv;
            case CalcToken::SUB : return $lv - $rv;
            case CalcToken::MUL : return $lv * $rv;
            case CalcToken::DIV : return $lv / $rv;
        }
    }
}


if (isset($argv) && isset($argv[0])) {
    echo "CalcPHP command line interface V1.0\n";

    while (true) {
        $line = readline("> ");

        readline_add_history($line);

        if ($line == 'exit')
            break;

        $start_time = microtime(true);

        $parser = new CalcParser($line);

        $ast = $parser->Run();

        $result = $ast->Exec();

        $end_time = microtime(true);

        printf("result: %f (%f sec)\n", $result, ($end_time - $start_time));
    }
}

?>
