<?php
namespace Craft;

class SuperTablePlugin extends BasePlugin
{
    /* --------------------------------------------------------------
    * PLUGIN INFO
    * ------------------------------------------------------------ */

    public function getName()
    {
        return Craft::t('Super Table');
    }

    public function getVersion()
    {
        return '0.3.8';
    }

    public function getDeveloper()
    {
        return 'S. Group';
    }

    public function getDeveloperUrl()
    {
        return 'http://sgroup.com.au';
    }

    public function onAfterInstall()
    {   
        $minBuild = '2615';

        if (craft()->getBuild() < $minBuild) {
            craft()->plugins->disablePlugin($this->getClassHandle());

            craft()->plugins->uninstallPlugin($this->getClassHandle());

            craft()->userSession->setError(Craft::t('{plugin} only works on Craft build {build} or higher', array(
                'plugin' => $this->getName(),
                'build' => $minBuild,
            )));
        }
    }


    /* --------------------------------------------------------------
    * HOOKS
    * ------------------------------------------------------------ */
 
}
