<?php
/*
 * This file is part of the RedKite CMS Application and it is distributed
 * under the MIT LICENSE. To use this application you must leave intact this copyright 
 * notice.
 *
 * Copyright (c) RedKite Labs <info@redkite-labs.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * For extra documentation and help please visit http://www.redkite-labs.com
 * 
 * @license    MIT LICENSE
 * 
 */

namespace RedKiteLabs\RedKiteCms\TwitterBootstrapBundle\Tests\Unit\Core\Form\Badge\Two;

use RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Tests\Unit\Core\Form\Base\BaseType;
use RedKiteLabs\RedKiteCms\TwitterBootstrapBundle\Core\Form\Badge\Two\BadgeType;

/**
 * BadgeTypeTest
 *
 * @author RedKite Labs <info@redkite-labs.com>
 */
class BadgeTypeTest extends BaseType
{
    protected $translatorDomain = 'TwitterBootstrapBundle';
    
    protected function configureFields()
    {
        return array(
            'badge_text',
            array(
                'name' => 'badge_type',
                'type' => 'choice', 
                'options' => array(
                    'label' => 'badge_block_type', 
                    'choices' => array('' => 'base', 'badge-info' => 'info', 'badge-success' => 'success', 'badge-warning' => 'warning', 'badge-important' => 'important', 'badge-inverse' => 'inverse')),
            ),
            'class',
            array(
                'name' => 'save',
                'type' => 'submit',
                'options' => array(
                    'label' => 'common_label_save', 
                    'attr' => array('class' => 'al_editor_save btn btn-primary')),
            ),
        );
    }
    
    protected function getForm()
    {
        return new BadgeType();
    }
    
    public function testDefaultOptions()
    {
        $this->setBaseResolver();

        $this->getForm()->setDefaultOptions($this->resolver);
    }
    
    public function testGetName()
    {
        $this->assertEquals('al_json_block', $this->getForm()->getName());
    }
}
