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
   @author    Vincent Mazzoni
   @co-author David Durieux
   @copyright Copyright (c) 2010-2012 FusionInventory team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      http://www.fusioninventory.org/
   @link      http://forge.fusioninventory.org/projects/fusioninventory-for-glpi/
   @since     2010
 
   ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginFusioninventorySetup {

   // Uninstallation function
   static function uninstall() {
      global $DB;

      CronTask::Unregister('fusioninventory');
      
      $PluginFusioninventorySetup  = new PluginFusioninventorySetup();
      $PluginFusioninventoryModule = new PluginFusioninventoryModule();
      $user = new User();
      $plugins_id = $PluginFusioninventoryModule->getModuleId("fusioninventory");

      if (class_exists('PluginFusioninventoryConfig')) {
         $fusioninventory_config      = new PluginFusioninventoryConfig();
         $users_id = $fusioninventory_config->getValue($plugins_id, 'users_id');
         $user->delete(array('id'=>$users_id), 1);
      }

      if (file_exists(GLPI_PLUGIN_DOC_DIR.'/fusioninventory')) {
         $PluginFusioninventorySetup->rrmdir(GLPI_PLUGIN_DOC_DIR.'/fusioninventory');
      }

      $query = "SHOW TABLES;";
      $result=$DB->query($query);
      while ($data=$DB->fetch_array($result)) {
         if ((strstr($data[0],"glpi_plugin_fusioninventory_"))
                 OR (strstr($data[0], "glpi_plugin_fusinvsnmp_"))
                 OR (strstr($data[0], "glpi_plugin_fusinvinventory_"))
                OR (strstr($data[0], "glpi_dropdown_plugin_fusioninventory"))
                OR (strstr($data[0], "glpi_plugin_tracker"))
                OR (strstr($data[0], "glpi_dropdown_plugin_tracker"))) {

            $query_delete = "DROP TABLE `".$data[0]."`;";
            $DB->query($query_delete) or die($DB->error());
         }
      }

      $query="DELETE FROM `glpi_displaypreferences`
              WHERE `itemtype` LIKE 'PluginFusioninventory%';";
      $DB->query($query) or die($DB->error());

      // Delete rules
      $Rule = new Rule();
      $a_rules = $Rule->find("`sub_type`='PluginFusioninventoryRuleImportEquipment'");
      foreach ($a_rules as $data) {
         $Rule->delete($data);
      }

      return true;
   }

   
   
   /**
    * Remove a directory and sub-directory
    * 
    * @param type $dir name of the directory
    */
   function rrmdir($dir) {
      $PluginFusioninventorySetup = new PluginFusioninventorySetup();

      if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
          if ($object != "." && $object != "..") {
            if (filetype($dir."/".$object) == "dir") {
               $PluginFusioninventorySetup->rrmdir($dir."/".$object);
            } else {
               unlink($dir."/".$object);
            }
          }
        }
        reset($objects);
        rmdir($dir);
      }
   }


   
   /**
    * Create rules (initialisation)
    */
   function initRules() {
      
      // Load classe (use for install and update
         if (!class_exists('PluginFusioninventoryUnknownDevice')) { // if plugin is unactive
            include(GLPI_ROOT . "/plugins/fusioninventory/inc/unknowndevice.class.php");
         }
         if (!class_exists('PluginFusioninventoryRuleImportEquipmentCollection')) { // if plugin is unactive
            include(GLPI_ROOT . "/plugins/fusioninventory/inc/ruleimportequipmentcollection.class.php");
         }
         if (!class_exists('PluginFusioninventoryRuleImportEquipment')) { // if plugin is unactive
            include(GLPI_ROOT . "/plugins/fusioninventory/inc/ruleimportequipment.class.php");
         }
      
      $ranking = 0;
      
     // Create rule for : Computer + serial + uuid
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='Computer serial + uuid';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "serial";
         $input['pattern']= 1;
         $input['condition']=10;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "serial";
         $input['pattern']= 1;
         $input['condition']=8;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "uuid";
         $input['pattern']= 1;
         $input['condition']=10;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "uuid";
         $input['pattern']= 1;
         $input['condition']=8;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "itemtype";
         $input['pattern']= 'Computer';
         $input['condition']=0;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);


     $ranking++;
     // Create rule for : Computer + serial
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='Computer serial';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "serial";
         $input['pattern']= 1;
         $input['condition']=10;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "serial";
         $input['pattern']= 1;
         $input['condition']=8;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "itemtype";
         $input['pattern']= 'Computer';
         $input['condition']=0;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);

      $ranking++;

     $ranking++;
     // Create rule for : Computer + mac
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='Computer mac';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "mac";
         $input['pattern']= 1;
         $input['condition']=10;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "mac";
         $input['pattern']= 1;
         $input['condition']=8;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "itemtype";
         $input['pattern']= 'Computer';
         $input['condition']=0;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);

     $ranking++;
     // Create rule for : Computer + name
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='Computer name';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "name";
         $input['pattern']= 1;
         $input['condition']=10;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "name";
         $input['pattern']= 1;
         $input['condition']=8;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "itemtype";
         $input['pattern']= 'Computer';
         $input['condition']=0;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);

      $ranking++;
      // Create rule for : Computer import
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='Computer import';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "itemtype";
         $input['pattern']= 'Computer';
         $input['condition']=0;
         $rulecriteria->add($input);
         
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "name";
         $input['pattern']= 1;
         $input['condition']=8;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);



     $ranking++;
     // Create rule for : Printer + serial
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='Printer serial';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "serial";
         $input['pattern']= 1;
         $input['condition']=10;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "serial";
         $input['pattern']= 1;
         $input['condition']=8;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "itemtype";
         $input['pattern']= 'Printer';
         $input['condition']=0;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);

      $ranking++;
      // Create rule for : Printer + mac
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='Printer mac';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "mac";
         $input['pattern']= 1;
         $input['condition']=10;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "mac";
         $input['pattern']= 1;
         $input['condition']=8;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "itemtype";
         $input['pattern']= 'Printer';
         $input['condition']=0;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);

      $ranking++;
      // Create rule for : Printer + name
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='Printer name';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "name";
         $input['pattern']= 1;
         $input['condition']=10;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "name";
         $input['pattern']= 1;
         $input['condition']=8;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "itemtype";
         $input['pattern']= 'Printer';
         $input['condition']=0;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);

      $ranking++;
      // Create rule for : NetworkEquipment + serial
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='NetworkEquipment serial';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "serial";
         $input['pattern']= 1;
         $input['condition']=10;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "serial";
         $input['pattern']= 1;
         $input['condition']=8;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "itemtype";
         $input['pattern']= 'NetworkEquipment';
         $input['condition']=0;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);

      $ranking++;
      // Create rule for : NetworkEquipment + mac
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='NetworkEquipment mac';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "mac";
         $input['pattern']= 1;
         $input['condition']=10;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "mac";
         $input['pattern']= 1;
         $input['condition']=8;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "itemtype";
         $input['pattern']= 'NetworkEquipment';
         $input['condition']=0;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);

      $ranking++;
      // Create rule for : NetworkEquipment import
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='NetworkEquipment import';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "itemtype";
         $input['pattern']= 'NetworkEquipment';
         $input['condition']=0;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);

      $ranking++;
      // Create rule for search serial in all DB
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='Find serial in all GLPI';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "serial";
         $input['pattern']= 1;
         $input['condition']=10;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "serial";
         $input['pattern']= 1;
         $input['condition']=8;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);


     $ranking++;
     // Create rule for search mac in all DB
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='Find mac in all GLPI';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "mac";
         $input['pattern']= 1;
         $input['condition']=10;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "mac";
         $input['pattern']= 1;
         $input['condition']=8;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);


     $ranking++;
     // Create rule for search name in all DB
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='Find name in all GLPI';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "name";
         $input['pattern']= 1;
         $input['condition']=10;
         $rulecriteria->add($input);

         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "name";
         $input['pattern']= 1;
         $input['condition']=8;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);


      $ranking++;
      // Create rule for import into unknown devices
      $rulecollection = new PluginFusioninventoryRuleImportEquipmentCollection();
      $input = array();
      $input['is_active']=1;
      $input['name']='Unknown device import';
      $input['match']='AND';
      $input['sub_type'] = 'PluginFusioninventoryRuleImportEquipment';
      $input['ranking'] = $ranking;
      $rule_id = $rulecollection->add($input);

         // Add criteria
         $rule = $rulecollection->getRuleClass();
         $rulecriteria = new RuleCriteria(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['criteria'] = "name";
         $input['pattern']= '*';
         $input['condition']=0;
         $rulecriteria->add($input);

         // Add action
         $ruleaction = new RuleAction(get_class($rule));
         $input = array();
         $input['rules_id'] = $rule_id;
         $input['action_type'] = 'assign';
         $input['field'] = '_fusion';
         $input['value'] = '0';
         $ruleaction->add($input);         
   }
   
   
   
   /**
    * Creation of FusionInventory user
    * 
    * @return int id of the user "plugin FusionInventory"
    */
   function createFusionInventoryUser() {
      $user = new User();
      $a_users = array();
      $a_users = $user->find("`name`='Plugin_FusionInventory'");
      if (count($a_users) == '0') {
         $input = array();
         $input['name'] = 'Plugin_FusionInventory';
         $input['password'] = mt_rand(30, 39);
         $input['firstname'] = "Plugin FusionInventory";
         return $user->add($input);
      } else {
         $user = current($a_users);
         return $user['id'];        
      }
   }
}

?>
