<?php

namespace App\Utils;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

class TranslationDumper
{
    /** @var ParameterBagInterface */
    private $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public function dump(string $locale = 'en')
    {
        $translationPath = $this->params->get('kernel.project_dir') . \DIRECTORY_SEPARATOR . 'translations';
        $translations = [];

        $finder = new Finder();

        foreach ($finder->files()->name("vue.$locale.yaml")->in($translationPath) as $file) {
            /** @var SplFileInfo $file */
            foreach (Yaml::parse($file->getContents()) as $key => $val) {
                $translations[$key] = $val;
            }
        }

        return [$locale => $translations];
    }
}
