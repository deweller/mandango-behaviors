<?php

/*
 * Copyright 2010 Pablo Díez <pablodip@gmail.com>
 *
 * This file is part of Mandango.
 *
 * Mandango is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Mandango is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with Mandango. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Mandango\Behavior;

use Mandango\Inflector;
use Mandango\Mondator\ClassExtension;
use Mandango\Mondator\Definition\Method;

/**
 * Sluggable.
 *
 * @author Pablo Díez <pablodip@gmail.com>
 */
class Sluggable extends ClassExtension
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->addRequiredOption('from_field');

        $this->addOptions(array(
            'slug_field' => 'slug',
            'unique'     => true,
            'update'     => false,
            'builder'    => array('\Mandango\Behavior\Util\SluggableUtil', 'slugify'),
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function doConfigClassProcess()
    {
        // field
        $this->configClass['fields'][$this->getOption('slug_field')] = 'string';

        // index
        if ($this->getOption('unique')) {
            $this->configClass['indexes'][] = array(
                'keys'    => array($this->getOption('slug_field') => 1),
                'options' => array('unique' => 1),
            );
        }

        // event
        $this->configClass['events']['preInsert'][] = 'updateSluggableSlug';
        if ($this->getOption('update')) {
            $this->configClass['events']['preUpdate'][] = 'updateSluggableSlug';
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doClassProcess()
    {
        // field
        $slugField = $this->getOption('slug_field');

        // update slug
        $fromField = $this->getOption('from_field');
        $fromFieldCamelized = Inflector::camelize($fromField);
        $slugFieldCamelized = Inflector::camelize($slugField);
        $builder = var_export($this->getOption('builder'), true);

        $uniqueCode = '';
        if ($this->getOption('unique')) {
            $uniqueCode = <<<EOF
        \$similarSlugs = array();
        foreach (\\{$this->class}::collection()
            ->find(array('$slugField' => new \MongoRegex('/^'.\$slug.'/')))
        as \$result) {
            \$similarSlugs[] = \$result['$slugField'];
        }

        \$i = 1;
        while (in_array(\$slug, \$similarSlugs)) {
            \$slug = \$proposal.'-'.++\$i;
        }
EOF;
        }

        $method = new Method('protected', 'updateSluggableSlug', '', <<<EOF
        \$slug = \$proposal = call_user_func($builder, \$this->get$fromFieldCamelized());

$uniqueCode

        \$this->set$slugFieldCamelized(\$slug);
EOF
        );
        $this->definitions['document_base']->addMethod($method);

        // repository ->findBySlug()
        $method = new Method('public', 'findBySlug', '$slug', <<<EOF
        return \$this->query(array('$slugField' => \$slug))->one();
EOF
        );
        $method->setDocComment(<<<EOF
    /**
     * Returns a document by slug.
     *
     * @param string \$slug The slug.
     *
     * @return mixed The document or null if it does not exist.
     */
EOF
        );
        $this->definitions['repository_base']->addMethod($method);
    }
}