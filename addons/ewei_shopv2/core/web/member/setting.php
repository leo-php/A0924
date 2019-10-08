<?php  if( !defined("IN_IA") ) 
{
	exit( "Access Denied" );
}
class Setting_EweiShopV2Page extends WebPage
{
	public function main() 
	{
		global $_W;
		
		$setting = m('common')->getSetData();
		include($this->template());
	}

}
?>