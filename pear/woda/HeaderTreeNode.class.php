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
 * 此类用于组织复杂的动态报表表头, 并提供HTML的THEAD代码生成功能
 *
 * @author Milo Liu<cutadra@gmail.com>
 * @package woda
 */
class HeaderTreeNode{
    public $field;
    public $label;
    public $children = array();
    public $parent;

    /**
     *
     * HTML Head Data and related factors 
     */
    public $htmlHeadData = array();
    public $htmlHeadMaxWidth = 0;
    public $htmlHeadMaxHeight = 0;
    
    // 定义一个不常用到的字符组合, 用于填充被合并过的表头单元格的数据元素
    const CELL_FILLER = '||--XXXXX--|';
    
    
    public static function instance(){
        $instance = new HeaderTreeNode();
        return $instance;
    }
    public function setField($field){
        $this->field = $field;
        return $this;
    }
    public function setLabel($label){
        $this->label = $label;
        return $this;
    }
    public function addChild(HeaderTreeNode $child, $prefix='zzz'){
        $child->parent = $this;
        $childId = $prefix . '.' . $child->field . $child->getLabel();
        $this->children[$childId] = $child;
        return $this;
    }
    public function getLabel(){
        if(!empty($this->label)){
            return $this->label;
        }else{
            return $this->field;
        }
    }

    public function getFamilyLabels(){
        $path = array();
        if(! empty($this->parent) ){
            $path = $this->parent->getFamilyLabels();
        }
        $path[] = $this->label;
        return $path;
    }
    
    public function toHtmlHeaderData(HeaderTreeNode $tree, $level, $idx, $ignore = FALSE){
        if(FALSE == $ignore){
             $this->htmlHeadData[$level][$idx] = $tree->getLabel();
             $this->htmlHeadMaxWidth = $this->htmlHeadMaxWidth < $idx ? $idx : $this->htmlHeadMaxWidth;
             $this->htmlHeadMaxHeight = $this->htmlHeadMaxHeight < $level ? $level : $this->htmlHeadMaxHeight;
         }
         
             
         ksort($tree->children);
         
         $i = 0;
         foreach($tree->children as $subTree){
             if($i > 0 ) $idx++;
             $idx = $this->toHtmlHeaderData($subTree, $level + 1, $idx);
             $i++;
         }
         
         return $idx;
    }
    
    public function generateTH($label, $rowspan = 1, $colspan= 1){
        $rsAtt = ( $rowspan > 1 ) ? ' rowspan= "' . $rowspan . '" ' : '';
        $csAtt = ( $colspan > 1 ) ? ' colspan= "' . $colspan . '" ' : '';
        $th = '<th' . $rsAtt . $csAtt . '>' . $label . '</th>';
        return $th;
    }
    
    public function generateHtmlTHead(){
        $thead = '<thead>';
        foreach($this->htmlHeadData as $rowId => $rowData){
            $thead .= '<tr>';
            foreach($rowData as $colId => $colData){
                //如果单元格已经被填充, 则直接跳过
                if(self::CELL_FILLER == $colData){
                    continue;
                }
                
                $colspan = 1;
                $rowspan = 1;
                
                // 向右填充, 以及合并相连的空白单元格
                for($x = $colId + 1; $x <= $this->htmlHeadMaxWidth; $x++){
                    //一旦发现单元格不为空, 立即停止合并
                    if(isset($this->htmlHeadData[$rowId][$x])){
                        break;
                    }
                    // 填充
                    $this->htmlHeadData[$rowId][$x] = self::CELL_FILLER;
                    // 合并
                    $colspan++;
                }
                
                // 向下填充, 以及合并相连的空白单元格
                for($y = $rowId + 1; $y <= $this->htmlHeadMaxHeight; $y++){
                    //一旦发现单元格不为空, 立即停止合并
                    if(isset($this->htmlHeadData[$y][$colId])){
                        break;
                    }
                    // 填充
                    $this->htmlHeadData[$y][$colId] = self::CELL_FILLER;
                    // 合并
                    $rowspan++;
                }
                
                $th = $this->generateTH($colData, $rowspan, $colspan);
                $thead .= $th;
            }
            $thead .= '</tr>';
        }
        $thead .=  '</thead>';
        return $thead;
    }
    
    /**
     * 按左序深度优先原则, 取出所有叶子节点
     */
    public function getLeaves(){
        if(empty($this->children)){
            return array($this);
        }else{
            $leaves = array();
            foreach($this->children as $child){
                $leaves = array_merge($leaves, $child->getLeaves());
            }
            return $leaves;
        }
    }
}