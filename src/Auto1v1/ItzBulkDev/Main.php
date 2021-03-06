<?php
namespace Auto1v1;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\CallbackTask;
use Auto1v1\Command\TouchItCommand;
use Auto1v1\Listener\PlayerTouchListener;
use Auto1v1\Listener\SignCreateListener;
use Auto1v1\Listener\SignDestroyListener;
use Auto1v1\Provider\SQLite3;
class Auto1v1 extends PluginBase{
    /** @var string */
    private $lang;
    /** @var SignManager */
    private $manager;
    /**
     * Call when enable
     */
    public function onEnable(){
        if(!file_exists($this->getDataFolder()."config.yml")){
            $this->saveDefaultConfig();
        }
        $this->reloadConfig();
        $this->reloadLang();
        $this->manager = new SignManager($this);
        if(class_exists(($class = "Auto1v1\\Provider\\".$this->getConfig()->get("Provider")))){
            $this->manager->setProvider(new $class($this));
        }else{
            $this->getLogger()->alert($this->getLang("provider.notfound"));
            $this->manager->setProvider(new SQLite3($this));
        }
        $this->getServer()->getPluginManager()->registerEvents(new PlayerTouchListener($this->manager), $this);
        $this->getServer()->getPluginManager()->registerEvents(new SignCreateListener($this->manager), $this);
        $this->getServer()->getPluginManager()->registerEvents(new SignDestroyListener($this->manager), $this);
        $this->getServer()->getCommandMap()->register("Auto1v1", new Auto1v1Command($this->manager));
        if($this->getConfig()->get("AutoUpdate"))
            $this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this->manager, "update"]), 20 * $this->getConfig()->get("ScheduleRepeatingPeriod"));
    }
    /**
     * @param $key
     * @return string
     */
    public function getLang($key){
        if(isset($this->lang[$key])){
            return $this->lang[$key];
        }else if(is_array($this->lang)){
            return "Key not found.";
        }
        $this->reloadLang();
        return $this->getLang($key);
    }
    /**
     * Call when disable
     */
    public function onDisable(){
        $this->manager->close();
    }
    /**
     * Use to reload language profile
     */
    public function reloadLang(){
        $this->lang = [];
        $stream = $this->getResource("language/".strtolower($this->getConfig()->get("language")).".lang");
        if(!$stream){
            $stream = $this->getResource("language/english.lang");
            if(!$stream){
                $this->getLogger()->error("Unable to open stream. Could not load TouchIt languages.");
                $this->getServer()->forceShutdown();
                return;
            }
            $this->getLogger()->notice("Language \"".$this->getConfig()->get("language")."\" not found.");
            $this->getLogger()->notice("Make sure your spelling was correct. Change this option at \"plugins/Auto1v1/config.yml\"");
        }
        while(!feof($stream)){
            $line = trim(fgets($stream));
            if((strlen($line) >= 3) and $line{0} !== "#" and ($pos = strpos($line, "=")) != false){
                $this->lang[substr($line, 0, $pos)] = substr($line, $pos + 1);
            }
        }
        @fclose($stream);
    }
    /**
     * Get the preloaded commands config folder
     * @return string
     */
    public function getPreloadedDataFolder(){
        $dir = $this->getDataFolder()."commands".DIRECTORY_SEPARATOR;
        @mkdir($dir);
        return $dir;
    }
}
