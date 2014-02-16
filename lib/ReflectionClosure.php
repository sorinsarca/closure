<?php
/* ===========================================================================
 * Opis Project
 * http://opis.io
 * ===========================================================================
 * Copyright (c) 2014 Opis Project
 * 
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure;

use Closure;
use ReflectionFunction;
use SplFileObject;


class ReflectionClosure extends ReflectionFunction
{
    protected $code;
    protected $static_vars;

    
    public function __construct(Closure $closure, $code = null, array $static_vars = array())
    {
        $this->code = $code;
        $this->static_vars = $static_vars;
        parent::__construct($closure);
    }
    
    public function getCode()
    {
        if($this->code == null)
        {
            $_file_ = $this->getFileName();
            if (strpos($_file_, ClosureStream::STREAM_PROTO . '://') === 0)
            {
                return $this->code = substr($_file_, strlen(ClosureStream::STREAM_PROTO) + 3);
            }
            $_dir_ = dirname($_file_);
            $_line_ = $this->getStartLine() - 1;
            
            $file = new SplFileObject($_file_);
            $file->seek($_line_);
            $code = '<?php ';
            $end_line = $this->getEndLine();
            while ($file->key() < $end_line)
            {
                $code .= $file->current();
                $file->next();
            }
            $file = null;
            $_file_ = var_export($_file_, true);
            $_dir_ = var_export($_dir_, true);
            $_namespace_ = var_export($this->getNamespaceName(), true);
            $tokens = token_get_all($code);
            $state = 'start';
            $open = 0;
            $code = '';
            $static_var = false;
            $sub_closure = false;
            $static = array();
            foreach($tokens as &$token)
            {
                // Replace magic constants
                if ($is_array = is_array($token))
                {
                    switch ($token[0])
                    {
                        case T_LINE:
                            $token[1] = $_line_ + $token[2];
                            break;
                        case T_DIR:
                            $token[1] = $_dir_;
                            break;
                        case T_FILE:
                            $token[1] = $_file_;
                            break;
                        case T_NS_C:
                            $token[1] = $_namespace_;
                            break;
                    }
                }
                
                switch($state)
                {
                    case 'start':
                        if($is_array && $token[0] === T_FUNCTION)
                        {
                            $code .= $token[1];
                            $state = 'function';
                        }
                        break;
                    case 'function':
                        if($is_array)
                        {
                            $code .= $token[1];
                            
                            if($token[0] === T_STRING)
                            {
                                $state = 'named_function';
                                $static = array();
                                $code = '';
                            }
                            
                        }
                        else
                        {
                            $code .= $token;
                            
                            if($token === '(')
                            {
                                $state = 'closure';
                            }
                        }
                        break;
                    case 'named_function':
                        if(!$is_array)
                        {
                            if($token === '{')
                            {
                                $open++;
                            }
                            elseif($token === '}')
                            {
                                if(--$open === 0)
                                {
                                    $state = 'start';
                                }
                            }
                        }
                        break;
                    case 'closure':
                        if(!$is_array)
                        {
                            $code .= $token;
                            if($token === '{')
                            {
                                $open++;
                                if ($sub_closure !== false)
                                {
                                    $sub_closure++;
                                }
                            }
                            elseif($token === '}')
                            {
                                if(--$open === 0)
                                {
                                    break 2;
                                }
                                if ($sub_closure !== false && --$sub_closure === 0)
                                {
                                    $sub_closure = false;
                                }
                            }
                        }
                        else
                        {
                            if ($token[0] == T_FUNCTION)
                            {
                                if ($sub_closure === false)
                                {
                                  $sub_closure = 0;  
                                }
                            }
                            elseif ($sub_closure === false)
                            {
                                if ($token[0] == T_STATIC)
                                {
                                    $static_var = true;
                                }
                                elseif ($static_var && $token[0] == T_VARIABLE)
                                {  
                                    $static[] = ltrim($token[1], '$');
                                    $static_var = false;
                                }
                            }
                            $code .= $token[1];
                        }
                        break;
                }   
            }
            $this->static_vars = $static;
            $this->code = $code;
        }
        return $this->code;
    }
    
    public function getStatic()
    {
        if ($this->code == null)
        {
            $this->getCode();
        }
        return $this->static_vars;
    }
    
}
