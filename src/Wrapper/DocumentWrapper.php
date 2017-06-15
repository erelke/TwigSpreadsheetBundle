<?php

namespace MewesK\TwigSpreadsheetBundle\Wrapper;

use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\BaseWriter;
use Symfony\Bridge\Twig\AppVariable;

/**
 * Class DocumentWrapper.
 */
class DocumentWrapper extends BaseWrapper
{
    /**
     * @var Spreadsheet|null
     */
    protected $object;
    /**
     * @var array
     */
    protected $attributes;

    /**
     * DocumentWrapper constructor.
     *
     * @param array             $context
     * @param \Twig_Environment $environment
     * @param array             $attributes
     */
    public function __construct(array $context, \Twig_Environment $environment, array $attributes = [])
    {
        parent::__construct($context, $environment);

        $this->object = null;
        $this->attributes = $attributes;
    }

    /**
     * @param array $properties
     *
     * @throws \RuntimeException
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function start(array $properties = [])
    {
        // load template
        if (isset($properties['template'])) {
            $templatePath = $this->expandPath($properties['template']);
            $reader = IOFactory::createReaderForFile($templatePath);
            $this->object = $reader->load($templatePath);
        }

        // create new
        else {
            $this->object = new Spreadsheet();
            $this->object->removeSheetByIndex(0);
        }

        $this->parameters['properties'] = $properties;

        $this->setProperties($properties);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function end()
    {
        $format = null;

        // try document property
        if (isset($this->parameters['format'])) {
            $format = $this->parameters['format'];
        }

         // try Symfony request
        elseif (isset($this->context['app'])) {
            /**
             * @var AppVariable
             */
            $appVariable = $this->context['app'];
            if ($appVariable instanceof AppVariable && $appVariable->getRequest() !== null) {
                $format = $appVariable->getRequest()->getRequestFormat();
            }
        }

        // set default
        if ($format === null || !is_string($format)) {
            $format = 'xlsx';
        }

        switch (strtolower($format)) {
            case 'csv':
                $writerType = 'Csv';
                break;
            case 'ods':
                $writerType = 'Ods';
                break;
            case 'pdf':
                $writerType = 'Pdf';
                if (!class_exists('mPDF')) {
                    throw new Exception('Error loading mPDF. Is mPDF correctly installed?');
                }
                Settings::setPdfRendererName(Settings::PDF_RENDERER_MPDF);
                break;
            case 'xls':
                $writerType = 'Xls';
                break;
            case 'xlsx':
                $writerType = 'Xlsx';
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unknown format "%s"', $format));
        }

        if ($this->object !== null) {
            if (
                $this->attributes['diskCachingDirectory'] !== null &&
                !file_exists($this->attributes['diskCachingDirectory'])
            ) {
                if (!@mkdir($this->attributes['diskCachingDirectory']) && !is_dir($this->attributes['diskCachingDirectory'])){
                    throw new \RuntimeException('Error creating the PhpSpreadsheet cache directory');
                }
            }

            /**
             * @var BaseWriter $writer
             */
            $writer = IOFactory::createWriter($this->object, $writerType);
            $writer->setPreCalculateFormulas($this->attributes['preCalculateFormulas'] ?? true);
            $writer->setUseDiskCaching(
                $this->attributes['diskCachingDirectory'] !== null,
                $this->attributes['diskCachingDirectory'] ?? null
            );
            $writer->save('php://output');
        }

        $this->object = null;
        $this->parameters = [];
    }

    /**
     * @return Spreadsheet|null
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @param Spreadsheet|null $object
     */
    public function setObject(Spreadsheet $object = null)
    {
        $this->object = $object;
    }

    /**
     * @return array
     */
    protected function configureMappings(): array
    {
        return [
            'category' => function ($value) { $this->object->getProperties()->setCategory($value); },
            'company' => function ($value) { $this->object->getProperties()->setCompany($value); },
            'created' => function ($value) { $this->object->getProperties()->setCreated($value); },
            'creator' => function ($value) { $this->object->getProperties()->setCreator($value); },
            'defaultStyle' => function ($value) { $this->object->getDefaultStyle()->applyFromArray($value); },
            'description' => function ($value) { $this->object->getProperties()->setDescription($value); },
            'format' => function ($value) { $this->parameters['format'] = $value; },
            'keywords' => function ($value) { $this->object->getProperties()->setKeywords($value); },
            'lastModifiedBy' => function ($value) { $this->object->getProperties()->setLastModifiedBy($value); },
            'manager' => function ($value) { $this->object->getProperties()->setManager($value); },
            'modified' => function ($value) { $this->object->getProperties()->setModified($value); },
            'security' => [
                'lockRevision' => function ($value) { $this->object->getSecurity()->setLockRevision($value); },
                'lockStructure' => function ($value) { $this->object->getSecurity()->setLockStructure($value); },
                'lockWindows' => function ($value) { $this->object->getSecurity()->setLockWindows($value); },
                'revisionsPassword' => function ($value) { $this->object->getSecurity()->setRevisionsPassword($value); },
                'workbookPassword' => function ($value) { $this->object->getSecurity()->setWorkbookPassword($value); },
            ],
            'subject' => function ($value) { $this->object->getProperties()->setSubject($value); },
            'template' => function ($value) { $this->parameters['template'] = $value; },
            'title' => function ($value) { $this->object->getProperties()->setTitle($value); },
        ];
    }

    /**
     * Resolves paths using Twig namespaces.
     * The path must start with the namespace.
     * Namespaces are case sensitive.
     *
     * @param string $path
     *
     * @return string
     */
    private function expandPath(string $path): string
    {
        $loader = $this->environment->getLoader();

        if ($loader instanceof \Twig_Loader_Filesystem && mb_strpos($path, '@') === 0) {
            /*
             * @var \Twig_Loader_Filesystem
             */
            foreach ($loader->getNamespaces() as $namespace) {
                if (mb_strpos($path, $namespace) === 1) {
                    foreach ($loader->getPaths($namespace) as $namespacePath) {
                        $expandedPathAttribute = str_replace('@'.$namespace, $namespacePath, $path);
                        if (file_exists($expandedPathAttribute)) {
                            return $expandedPathAttribute;
                        }
                    }
                }
            }
        }

        return $path;
    }
}