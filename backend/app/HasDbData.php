<?php
namespace App;

use App\Helpers\BaseHelper;

trait HasDbData
{
    // must have $data property
    private ?array $data = null;
    
    protected function _loadDataFromDb( $dbData ):void{
        $this->data = BaseHelper::fromDbJson( $dbData );
    }
    protected function _encodeDataForDb():?string{
        return BaseHelper::toDbJson( $this->data );
    }
    
    protected function _hasDataItem( string $name ):bool {
         return is_array($this->data) && array_key_exists($name, $this->data);
    }
    protected function _getAllData():?array  {
        return $this->data;
    }
    protected function _setAllData( ?array $data ):void {
        $this->data = $data;
    }
    
    protected function _getDataItem( string $name ){
        return $this->data[$name] ?? null;
    }
    protected function _getArrayDataItem( string $name ):array{
        return BaseHelper::forceArray($this->_getDataItem($name));
    }
    protected function _getDataSub( string $name , string $sub){
        return $this->data[$name][$sub] ?? null;
    }
    protected function _hasDataSub( string $name , string $sub):bool{
         return $this->_hasDataItem( $name) && is_array($this->data[$name]) && array_key_exists($sub, $this->data[$name]);
    }
    
    protected function _setDataItem( string $name , $value):void{
        if( $value === null){
            unset($this->data[$name]);
        }
        else {
            $this->data[$name] = BaseHelper::storableOrFail($value);
        }
    }
    protected function _setDataSub( string $name , string $sub, $value ):void{
        if( $value === null){
            unset( $this->data[$name][$sub] );
            if( empty($this->data[$name])){
                unset( $this->data[$name] );
            }
        }
        else{
            $this->data[$name][$sub] = BaseHelper::storableOrFail($value);
        }
    }
}
