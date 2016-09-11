<?php
/*
 * Copyright 2015 Milo Liu<cutadra@gmail.com>. 
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without 
 * modification, are permitted provided that the following conditions are met:
 *    1. Redistributions of source code must retain the above copyright notice, 
 *       this list of conditions and the following disclaimer.
 * 
 *    2. Redistributions in binary form must reproduce the above copyright 
 *       notice, this list of conditions and the following disclaimer in the 
 *       documentation and/or other materials provided with the distribution.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDER AND CONTRIBUTORS "AS IS" 
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE 
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE 
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE 
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR 
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF 
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS 
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN 
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) 
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE 
 * POSSIBILITY OF SUCH DAMAGE.
 * 
 * The views and conclusions contained in the software and documentation are 
 * those of the authors and should not be interpreted as representing official 
 * policies, either expressed or implied, of the copyright holder.
 */
 
 /**
 * @author Milo Liu<cutadra@gmail.com>
 * @package woda
 * 
 * 此类用于数据透视表中设定分组字段的具体细节
 */
class PovitFieldSetting{
    const TYPE_COL = 'COLUMN_GROUP';
    const TYPE_ROW = 'ROW_GROUP';
    const TYPE_STAT = 'STATS_FUN';

    const FUN_MIN = 'min'; // => '最小值',
    const FUN_MAX = 'max'; //  => '最大值',
    const FUN_MISSING = 'missing'; //  => '空值数量',,
    const FUN_COUNT = 'count'; //  => '计数',
    const FUN_SUM = 'sum'; //  => '总数',
    const FUN_SUMOFSQUARES = 'sumOfSquares'; //  => '平方和',
    const FUN_MEAN = 'mean'; //  => '平均值',
    const FUN_STDDEV = 'stddev'; //  => '标准偏差'
    
    public $field;
    public $type;  // TYPE_COL or TYPE_ROW
    public $hasSubtotal; // 是否启用分组小计
    public $ingoreZero; // 如果对应的统计项目为零, 是否忽略该统计项目, 使不出现在最终的结果之中
    public $sort;
    public $fun;

    public $label; //显示在表头上的标签, 如果此项不设置, 默认使用field为表头标签
    
    /**
     * 代替构造方法, 便于编写链式代码
     */
    public static function instance(){
        $instance = new PovitFieldSetting();
        return $instance; 
    }

    //=================================
    // 一系列支持链式调用的属性设置方法
    // --------------------------------

    public function setField($field){
        $this->field = $field;
        return $this;
    }

    public function setType($type){
        $this->type = $type;
        
        // 统计字段, 默认使用sum统计函数
        if(self::TYPE_STAT == $type){
           $this->fun = self::FUN_SUM; 
        }
        return $this;
    }

    public function setLabel($label){
        $this->label = $label;
        return $this;
    }

    public function setHasSubtotal($flag){
        $this->hasSubtotal= $flag;
        return $this;
    }

    public function setIngoreZero($flag){
        $this->ingoreZero = $flag;
        return $this;
    }

    public function setSort($sort){
        $this->sort = $sort;
        return $this;
    }
    
    public function setFun($fun){
        $this->fun = $fun;
        return $this;
    }
    
    /**
     * 如果没有专门设置label, 则使用field代替label
     */
    public function getLabel(){
        if(!empty($this->label)){
            return $this->label;
        }else{
            // 对于统计字段, 需要同时返回字段名称和统计函数名称
            if(self::TYPE_STAT == $this->type){
                return $this->field . ' ' . $this->fun;
            }else{
                return $this->field;
            }
        }
    }
}