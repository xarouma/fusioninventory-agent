<?php

/*
   ------------------------------------------------------------------------
   FusionInventory
   Copyright (C) 2010-2012 by the FusionInventory Development Team.

   http://www.fusioninventory.org/   http://forge.fusioninventory.org/
   ------------------------------------------------------------------------

   LICENSE

   This file is part of FusionInventory project.

   FusionInventory is free software: you can redistribute it and/or modify
   it under the terms of the GNU Affero General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   FusionInventory is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
   GNU Affero General Public License for more details.

   You should have received a copy of the GNU Affero General Public License
   along with Behaviors. If not, see <http://www.gnu.org/licenses/>.

   ------------------------------------------------------------------------

   @package   FusionInventory
   @author    David Durieux
   @co-author 
   @copyright Copyright (c) 2010-2012 FusionInventory team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      http://www.fusioninventory.org/
   @link      http://forge.fusioninventory.org/projects/fusioninventory-for-glpi/
   @since     2010
 
   ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   define('GLPI_ROOT', '../../..');
}

include (GLPI_ROOT."/inc/includes.php");

commonHeader($LANG['plugin_fusioninventory']['title'][0],$_SERVER["PHP_SELF"],"plugins","fusioninventory","configsecurity");

PluginFusioninventoryProfile::checkRight("fusinvsnmp", "configsecurity","r");

$PluginFusioninventoryConfig = new PluginFusioninventoryConfig();

PluginFusioninventoryMenu::displayMenu("mini");

$plugins_id = PluginFusioninventoryModule::getModuleId('fusinvsnmp');
// Forms for FILE
if ($PluginFusioninventoryConfig->getValue($plugins_id, "storagesnmpauth") == "file") {
   if (!isset($_GET["id"])) {
      $PluginFusinvsnmpConfigSecurity = new PluginFusinvsnmpConfigSecurity();
      echo $PluginFusinvsnmpConfigSecurity->plugin_fusioninventory_snmp_connections();
   }
} else if ($PluginFusioninventoryConfig->getValue($plugins_id, "storagesnmpauth") == "DB") {
   // Forms for DB
   
   $_GET['target']="configsecurity.php";
   
   Search::show('PluginFusinvsnmpConfigSecurity');
} else {
   echo $LANG['plugin_fusioninventory']['functionalities'][19];
}

commonFooter();

?>