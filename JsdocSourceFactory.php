<?php

namespace Omnidocs\Jsdoc;

use Omnidocs\Config;
use Omnidocs\Exception\InvalidConfigException;
use Omnidocs\Html\Conventions;
use Omnidocs\SourceFactory;
use Omnidocs\SourceInterface;

/**
 * The "jsdoc" source:
 *
 *   file    the JSON `jsdoc -X <sources>` wrote                                  required
 *   root    where the sources were when JSDoc read them ("/flowjs"), so that each file
 *           is named as the repository names it
 *   title   the reference's title                                                 "API reference"
 *   order   the globals first, in this order
 *   groups  a group's title => its globals: the index's cards and the tree by group
 *   links   a global => its links on the index (label => address: its manual, its demo)
 *   labels  the callouts' words
 */
final class JsdocSourceFactory extends SourceFactory
{
    public function getName(): string
    {
        return 'jsdoc';
    }

    protected function populate(Config $config): void
    {
        $config->defaults([
            'root' => '',
            'title' => 'API reference',
            'order' => [],
            'groups' => [],
            'links' => [],
            'labels' => [],
            'omnidocs.required_options' => ['file'],
        ]);
    }

    protected function build(Config $config): SourceInterface
    {
        $file = (string) $config->string('file');
        if (!is_file($file)) {
            throw new InvalidConfigException(\sprintf('The "jsdoc" source reads a file that is not there: %s.', $file));
        }

        return new JsdocSource($file, (string) $config->string('root'), (string) $config->string('title'), $config->list('order'), new Conventions($config->map('labels')), self::nested($config->get('groups', []), true), self::nested($config->get('links', []), false));
    }

    /**
     * A map of lists (groups: title => globals) or of maps (links: global => label => address), strings only.
     *
     * @return array<string, array<array-key, string>>
     */
    private static function nested(mixed $value, bool $lists): array
    {
        $out = [];
        foreach (\is_array($value) ? $value : [] as $key => $inner) {
            if (!\is_array($inner)) {
                continue;
            }
            $clean = [];
            foreach ($inner as $k => $v) {
                if (\is_scalar($v)) {
                    $clean[(string) $k] = (string) $v;
                }
            }
            $out[(string) $key] = $lists ? array_values($clean) : $clean;
        }

        return $out;
    }
}
