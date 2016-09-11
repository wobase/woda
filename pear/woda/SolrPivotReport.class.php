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
 * 此类用于根据行以及列组字段设置, 将Solr的facet.povit结合stats统计查询出来的结果, 转换成一
 * 个动态的二级数据透视表结果
 * @author Milo Liu<cutadra@gmail.com>
 */
class SolrPovitReport{
    /** 
     * 二维表数据
     */
    public  $data = array();
    public  $binding = array();

    /**
     * 分组字段数组
     * @var array<PovitFieldSetting>
     */ 
    public  $groupFields;
    public  $statFields;

    /**
     * 表头树的根节点
     * @var HeaderTreeNode
     */
    public $headerNode = array();


    /**
     * 设置一个字段, 当此字段的子集被全部分处理完成后, 
     * 则表示二维数据表中的一行数据已经完整, 可以产生
     * 一个新的数据行
     */
    public $rowStopField;


    /**
     * 行数据汇集模式开关
     * 默认关闭, 仅在进入rowStopField的子节点前临时开启, 并在处理完子节点后立即关闭
     */
    public $rowDataCollectModel = false;
    
    /**
     * 内置分组字段的设置与rowStopField的设定逻辑
     * 
     * @param PovitFieldSetting $groupField
     * @return \SolrPovitReport
     */
    public function addGroupField(PovitFieldSetting $groupField){
        
        // 如果没有设定field, 则不添加给定的分组字段
        if(empty($groupField->field)){
            return $this;
        }
        
        // 如果字段类型不在指定范围之内, 不添加指定字段
        if (PovitFieldSetting::TYPE_ROW != $groupField->type 
                && PovitFieldSetting::TYPE_COL != $groupField->type) {
            return $this;
        }

        $this->groupFields[$groupField->field] = $groupField;
        if(PovitFieldSetting::TYPE_ROW == $groupField->type){
            $this->rowStopField = $groupField->field;
        }
        return $this;
    }
    
    /**
     * 添加统计字段设置
     * @param PovitFieldSetting $statField
     * @return \SolrPovitReport
     */
    public function addStatField(PovitFieldSetting $statField){
        //============
        // 模糊化容错处理
        if(empty($statField->field)){
            return $this;
        }
        if(PovitFieldSetting::TYPE_STAT != $statField->type){
            return $this;
        }
        if(empty($statField->fun)){
            return $this;
        }
        //=================
        
        $this->statFields[$statField->field][$statField->fun] = $statField;
        return $this;
    }

    public function orgReport($pivotArray, HeaderTreeNode $headerRoot,  $rowData=array()){
        foreach($pivotArray as $subPivotData){
            // 如何标识出一行数据?
            // 此处采用临时行的方式, 推迟至所有的rowField出现时, 才构造一个真实的报表行
            // 在出现首个rowField时, 构造一个新的table, dataRow
            $currentField = $subPivotData->field;
            $currentValue = $subPivotData->value;
            $fieldSetting = $this->groupFields[$currentField];
            $nextRoot = $headerRoot;

            switch($fieldSetting->type){
                // rowField
            case PovitFieldSetting::TYPE_ROW :
                // [data]
                // - 直接取值, 加入table的行中
                $rowData[$currentField] = $currentValue;

                // [header]
                // - 直接取字段名, 加入表头的第一级
                $headerId = $fieldSetting->field;
                if(! array_key_exists($headerId, $this->headerNode) ){
                    $rowHeaderNode = HeaderTreeNode::instance()->setField($fieldSetting->field)->setLabel($fieldSetting->getLabel());
                    $headerRoot->addChild($rowHeaderNode, $fieldSetting->sort);
                    $this->headerNode[$headerId] = $rowHeaderNode;
                }
                break;

                // colField
            case PovitFieldSetting::TYPE_COL :
                // [header]
                // - 取值:
                //  i. 如果是父级为rowField, 则加入表头的第一级
                //  ii. 否则, 则加入上一个colField的子级
                $familyLabels = $headerRoot->getFamilyLabels();
                $familyLabels[] = $currentValue;
                $headerId = implode('@@', $familyLabels);

                if(! array_key_exists($headerId, $this->headerNode) ) {
                    $colHeaderNode = HeaderTreeNode::instance()
                        ->setField($currentField)
                        ->setLabel($currentValue);
                    $headerRoot->addChild($colHeaderNode, $fieldSetting->sort);
                    $this->headerNode[$headerId] = $colHeaderNode;
                }

                $nextRoot = $this->headerNode[$headerId];


                // [data]
                // - 取值, 压入名称堆栈, 递归子交叉数据集
                // - 一旦没有子交叉数据集存在, 则循环所有的stats项目, 组合名称堆栈中的内容, 将统计值加入到table的行中
                //   (此处需要根据colField的ingore_zero设置处理统计值为0的结果)
                // [header]添加stats.field 为当前colField的子级
                if(!isset($subPivotData->pivot)){
                    foreach($subPivotData->stats->stats_fields as $sField => $statData){
                        //$statSetting = $this->statFields[$sField];
                        foreach($this->statFields[$sField] as $fun => $statFieldSetting){
                            $statValue = $statData[$fun];
                            if($fieldSetting->ingoreZero && 0 == $statValue) {
                                continue;
                            }
                            $statHeaderId = $headerId . '@@' . $sField . '@@' . $fun;
                            if(! array_key_exists($statHeaderId, $this->headerNode) ){
                                $statHeaderNode = HeaderTreeNode::instance()
                                    ->setField($statHeaderId)
                                    ->setLabel($statFieldSetting->getLabel());
                                $nextRoot->addChild($statHeaderNode);
                                $this->headerNode[$statHeaderId] = $statHeaderNode;
                            }
                            $rowData[$statHeaderId] = $statValue;
                        }
                    }
                } 
                break;
            }

            // 向下传递处理
            if(isset($subPivotData->pivot)){
                $rowDataBackup = array();
                // 首先判定是否需要开启行数据的汇集模式
                if($this->rowStopField == $currentField){
                    //$this->rowDataCollectModel = true;
                    $rowDataBackup = $rowData;
                }               


                // 处理下级结点
                // ------------
                $rowData = $this->orgReport($subPivotData->pivot, $nextRoot, $rowData);

                // 如果识别到下级的节点的has_subtotal为true时
                if(isset($subPivotData->pivot) 
                    && is_array($subPivotData->pivot)
                    && count($subPivotData->pivot) > 0 ){
                   
                    $firstChild = $subPivotData->pivot[0];
                    $subFieldName = $firstChild->field;
                    $subFieldSetting = $this->groupFields[$subFieldName];
                    if($subFieldSetting->hasSubtotal){
                        // FOREACH 所有的statField
                        foreach($subPivotData->stats->stats_fields as $subTotalStatField => $subTotalStatData){
                          
                            foreach($this->statFields[$subTotalStatField] as $fun => $subTotalStatFieldSetting){
                                //var_dump($fun);
                                //print_r($subTotalStatFieldSetting);
                                //print_r($subTotalStatData);
                                //exit;
                                $subTotalValue = $subTotalStatData[$fun];

                                //      此处需要结合colField的ingore_zero设置进行判定
                                //      IF ingore_zero == TRUE AND 相应的统计结果为 0  THEN
                                //          SKIP
                                //      ENDIF
                                if($subFieldSetting->ingoreZero && 0 == $subTotalValue){
                                    continue;
                                }
                                $familyLabels = $nextRoot->getFamilyLabels();
                                $subTotalFieldId = implode('@@', $familyLabels) . '@@' . $subTotalStatField . '@@subtotal@@' . $fun;
                                if(!array_key_exists($subTotalFieldId, $this->headerNode)){ 
                                    //      [header]
                                    //      将统计项的小计名称添加为子节点
                                    $subTotalHeaderNode = HeaderTreeNode::instance()
                                        ->setField($subTotalFieldId)
                                        ->setLabel($subTotalStatFieldSetting->getLabel() . '<br/>汇总');
                                    $nextRoot->addChild($subTotalHeaderNode);
                                    $this->headerNode[$subTotalFieldId] = $subTotalHeaderNode;
                                }
                                //      [data]
                                //      将统计项的统计值添加到tabler行中
                                $rowData[$subTotalFieldId] = $subTotalValue;
                                //var_dump($rowData);                                // ENDFOREACH
                            }
                        }
                    }
                }

                if($this->rowStopField == $currentField){
                    // 检查是否已经构成一个完整的行级数据集
                    /// 如果已经到达rowStopField, 则将rowData做为新的一行插入至报表中         
                    $this->data[] = $rowData;

                    //处理完成子节点, 立即关闭数据汇集模式 
                    //$this->rowDataCollectModel = false;
                    //清理掉当前行数据中的非公共内容, 为生成下一行数据做准备
                    $rowData = $rowDataBackup;
                }
            }
        }
        return $rowData;
    }
}
