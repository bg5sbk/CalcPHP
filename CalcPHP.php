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

        // Ignore White Space
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
            case '.' : $this->curr_tok = CalcToken::DOT;    $this->curr_pos += 1; return;
            case '+' : $this->curr_tok = CalcToken::ADD;    $this->curr_pos += 1; return;
            case '*' : $this->curr_tok = CalcToken::MUL;    $this->curr_pos += 1; return;
            case '/' : $this->curr_tok = CalcToken::DIV;    $this->curr_pos += 1; return;
            case '(' : $this->curr_tok = CalcToken::LBK;    $this->curr_pos += 1; return;
            case ')' : $this->curr_tok = CalcToken::RBK;    $this->curr_pos += 1; return;
            case ',' : $this->curr_tok = CalcToken::COMMA;  $this->curr_pos += 1; return;
            case '=' : $this->curr_tok = CalcToken::ASSIGN; $this->curr_pos += 1; return;
            case '-' :
                if ($this->curr_pos < $this->code_end - 2 && 
                    $this->code[$this->curr_pos + 1] == '>') {
                    $this->curr_tok = CalcToken::ARROW;
                    $this->curr_pos += 2;
                } else {
                    $this->curr_tok = CalcToken::SUB;
                    $this->curr_pos += 1; 
                }
                return;
        }

        // Identifier
        if ($c == '_' || ctype_alpha($c)) {
            $id_begin = $this->curr_pos;
            $id_length = 1;

            while (true) {
                $this->curr_pos += 1;

                if ($this->curr_pos == $this->code_end)
                    break;

                $c = $this->code[$this->curr_pos];

                if ($c != '_' && !ctype_alpha($c) && !ctype_digit($c))
                    break;

                $id_length += 1;
            }

            $this->curr_val = substr($this->code, $id_begin, $id_length);

            switch ($this->curr_val) {
                case 'fun' :
                    $this->curr_tok = CalcToken::FUN;
                    break;
                case 'end' :
                    $this->curr_tok = CalcToken::END;
                    break;
                default:
                    $this->curr_tok = CalcToken::ID;
            }
            
            return;
        }

        // Numeric
        if (ctype_digit($c)) {
            $num_begin = $this->curr_pos;
            $num_length = 1;

            $has_dot = $c == '.';

            while (true) {
                $this->curr_pos += 1;

                if ($this->curr_pos == $this->code_end)
                    break;

                $c = $this->code[$this->curr_pos];

                if (!ctype_digit($c)) {
                    if ($has_dot)
                        break;
                
                    if ($c == '.' && $this->curr_pos != $this->code_end - 1 && ctype_digit($this->code[$this->curr_pos + 1]))
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

        throw new Exception("unknow token '$c'");
    }
}

class CalcParser
{
    public function __construct($code, $state) {
        $this->state = $state;
        $this->lexer = new CalcLexer($code);
    }

    private $state;
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

    public function Parse() {
        $seglist = array();

        while (true) {
            $explist = $this->ParseSta();

            $stalist[] = $explist;

            if ($this->CurrentToken() == CalcToken::EOF)
                break;
        }

        return new CalcDoc($this->state, $stalist);
    }

    private function ParseSta() {
        $explist = $this->ParseExpList();
        
        if ($this->CurrentToken() != CalcToken::DOT) {
            $this->Error("missing '.' at the end of statement");
        }

        $this->NextToken();

        return $explist;
    }

    private function ParseExpList() {
        $explist = array();

        while (true) {
            $exp = $this->ParseExp(0);

            $explist[] = $exp;

            if ($this->CurrentToken() != CalcToken::COMMA)
                break;
        }

        return $explist;
    }

    private function ParseExp($pri) {
        $this->NextToken();

        if ($this->CurrentToken() == CalcToken::FUN) {
            $this->NextToken();
            
            return $this->ParseFun();
        } else {
            $lh = $this->ParseUnary();

            return $this->ParseRightSide($pri, $lh);
        }
    }

    private function ParseFun() {
        if ($this->CurrentToken() != CalcToken::LBK) {
            $this->Error("mission '('");
        }
        
        $this->NextToken();
        
        $arglist = array();
        
        while (true) {
            if ($this->CurrentToken() != CalcToken::ID)
                break;
            
            $arglist[] = $this->CurrentValue();
            
            $this->NextToken();
            
            if ($this->CurrentToken() != CalcToken::COMMA)
                break;
            
            $this->NextToken();
        }
        
        if ($this->CurrentToken() != CalcToken::RBK) {
            $this->Error("missing ')'");
        }
        
        $this->NextToken();
        
        if ($this->CurrentToken() != CalcToken::ARROW) {
            $this->Error("missing '->'");
        }
        
        $explist = $this->ParseExpList();
        
        if ($this->CurrentToken() != CalcToken::END) {
            $this->Error("mission 'end'");
        }
        
        $this->NextToken();
        
        $fun = new CalcFunAST($arglist, $explist);
        
        return $fun;
    }

    private function ParseUnary() {
        if ($this->CurrentToken() == CalcToken::LBK) {
            $ast = $this->ParseExp(0);

            if ($this->CurrentToken() != CalcToken::RBK) {
                $this->Error("missing ')'");
            }

            $this->NextToken();

            return $ast;
        } else if ($this->CurrentToken() == CalcToken::NUM) {
            $ast = new CalcNumAST($this->CurrentValue());
            
            $this->NextToken();

            return $ast;
        } else if ($this->CurrentToken() == CalcToken::ID) {
            $name = $this->CurrentValue();

            $this->NextToken();

            if ($this->CurrentToken() == CalcToken::LBK) {
                $arglist = array();

                while (true) {
                    $arg = $this->ParseExp(0);

                    if ($arg)
                        $arglist[] = $arg;

                    if ($this->CurrentToken() != CalcToken::COMMA)
                        break;
                }

                if ($this->CurrentToken() != CalcToken::RBK) {
                    $this->Error("mission ')'");
                }

                $this->NextToken();

                $ast = new CalcCallAST($name, $arglist);

                return $ast;    
            } else {
                $ast = new CalcVarAST($name);

                return $ast;
            }
        }

        return null;
    }

    private function ParseRightSide($pri, $lh) {
        while (true) {
            if (!CalcToken::IsBinOp($this->CurrentToken())) {
                return $lh;
            }

            $pri2 = $this->CurrentToken();

            if ($pri >= $pri2 && $pri2 != CalcToken::ASSIGN)
                return $lh;

            $rh = $this->ParseExp($pri2);
        
            $lh = new CalcBinAST($pri2, $lh, $rh);
        }
    }

    private function Error($msg) {
        throw new Exception($msg);
    }
}

class CalcToken {
    const EOF    = 1;   // End Of File
    const NUM    = 2;   // [0-9]+(\.[0-9]+)?
    const ID     = 3;   // [_a-zA-Z][_0-9a-zA-Z]*
    const DOT    = 4;   // .
    const COMMA  = 5;   // ,
    const LBK    = 6;   // (
    const RBK    = 7;   // )    
    const FUN    = 10;  // fun
    const ARROW  = 11;  // ->
    const END    = 12;  // end

                        // Binary Operators
    const ASSIGN = 20;  // =
    const ADD = 21;     // +
    const SUB = 22;     // -
    const MUL = 23;     // *
    const DIV = 24;     // /

    public static function IsBinOp($token) {
        return (self::ASSIGN <= $token) && ($token <= self::DIV);
    }
}

class CalcState {
    public function __construct($parent = null) {
        $this->parent = $parent;
    }

    private $parent;
    private $var_table = array();
       
    public function SetVar($id, $val) {
        $this->var_table[$id] = $val;
    }
    
    public function GetVar($id) {
        if (isset($this->var_table[$id]))
            return $this->var_table[$id];

        if ($this->parent) {
            $var = $this->parent->GetVar2($id);

            if ($var != null)
                return $var;
        }

        $this->var_table[$id] = 0;

        return 0;
    }

    private function GetVar2($id) {
        if (isset($this->var_table[$id]))
            return $this->var_table[$id];

        if ($this->parent)
            return $this->parent->GetVar2($id);

        return null; 
    }
}

class CalcDoc {
    public function __construct($state, $stalist) {
        $this->state = $state;
        $this->stalist = $stalist;
    }

    private $state;
    private $stalist;

    public function Exec() {
        foreach ($this->stalist as $explist) {
            foreach ($explist as $exp) {
                $result = $exp->Exec($this->state);
            }
        }
        return $result;
    }
}

class CalcVarAST {
    public function __construct($name) {
        $this->name = $name;
    }

    private $name;

    public function SetValue($state, $val) {
        $state->SetVar($this->name, $val);
    }

    public function Exec($state) {
        return $state->GetVar($this->name);
    }
}

class CalcNumAST {
    public function __construct($value) {
        $this->value = $value;
    }

    private $value;

    public function Exec($state) {
        return $this->value;
    }
}

class CalcBinAST {
    public function __construct($op, $lh, $rh) {
        $this->op = $op;
        $this->lh = $lh;
        $this->rh = $rh;
    }

    private $op;
    private $lh;
    private $rh;

    public function Exec($state) {
        if ($this->op == CalcToken::ASSIGN) {
            $rv = $this->rh->Exec($state);
            $this->lh->SetValue($state, $rv);
            return $rv;
        } else {
            $lv = $this->lh->Exec($state);
            $rv = $this->rh->Exec($state);

            switch ($this->op) {
                case CalcToken::ADD : return $lv + $rv;
                case CalcToken::SUB : return $lv - $rv;
                case CalcToken::MUL : return $lv * $rv;
                case CalcToken::DIV : return $lv / $rv;
            }
        }
    }
}

class CalcFunAST {
    public function __construct($arglist, $explist) {
        $this->arglist = $arglist;
        $this->explist = $explist;
    }

    private $id;
    private $arglist;
    private $explist;

    public function Exec($state) {
       return $this;
    }

    public function Call($state, $arglist) {
        $call_state = new CalcState($state);

        $arg_count = count($this->arglist);

        for ($i = 0; $i < $arg_count; $i ++) {
            $arg = $arglist[$i];
            $arg_id = $this->arglist[$i];
            $arg_val = $arg->Exec($state);

            $call_state->SetVar($arg_id, $arg_val);
        }

        foreach ($this->explist as $exp) {
            $result = $exp->Exec($call_state);
        }

        return $result;
    }
}

class CalcCallAST {
    public function __construct($name, $arglist) {
        $this->name = $name;
        $this->arglist = $arglist;
    }

    private $name;
    private $arglist;

    public function Exec($state) {
        $fun = $state->GetVar($this->name);

        return $fun->Call($state, $this->arglist);
    }
}


if (isset($argv) && isset($argv[0])) {
    echo "CalcPHP command line interface V1.0\n";

    $state = new CalcState();

    while (true) {
        $line = readline("> ");

        readline_add_history($line);

        if ($line == 'exit')
            break;

        try {
            $start_time = microtime(true);

            $parser = new CalcParser($line, $state);

            $doc = $parser->Parse();

            $result = $doc->Exec();

            $end_time = microtime(true);

            printf("result: %f (%f sec)\n", $result, ($end_time - $start_time));
        } catch(Exception $ex) {
            printf("error: %s\n", $ex->getMessage());            
        }
    }
}

?>
