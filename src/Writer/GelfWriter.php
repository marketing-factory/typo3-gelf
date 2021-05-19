<?php

declare(strict_types=1);

namespace Mfc\Typo3\GelfWriter\Writer;

use Gelf\Message;
use Gelf\Publisher;
use Gelf\Transport\HttpTransport;
use Gelf\Transport\TcpTransport;
use Gelf\Transport\UdpTransport;
use TYPO3\CMS\Core\Log\Exception\InvalidLogWriterConfigurationException;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogRecord;
use TYPO3\CMS\Core\Log\Writer\AbstractWriter;
use TYPO3\CMS\Core\Log\Writer\WriterInterface;

/**
 * Class GelfWriter
 * @package Mfc\Typo3\GelfWriter\src\Writer
 * @author Christian Spoo <christian.spoo@marketing-factory.de>
 */
class GelfWriter extends AbstractWriter implements WriterInterface
{
    private Publisher $publisher;

    protected string $hostname = '';
    protected int $port = 12201;
    protected string $protocol = 'tcp';
    protected string $facility = 'typo3';
    protected array $additionalData = [];

    public function __construct(array $options = [])
    {
        parent::__construct($options);

        $this->publisher = $this->createPublisher();
    }

    public function writeLog(LogRecord $record)
    {
        $host = php_uname('n');
        $recordLevel = $record->getLevel();
        $logLevel = is_numeric($recordLevel) ? LogLevel::getName((int)$recordLevel) : $recordLevel;
        $shortMessage = $record->getMessage();
        $fullMessage = $record->getMessage();
        $data = array_merge(
            $record->getData(),
            [
                'component' => $record->getComponent(),
                'request_id' => $record->getRequestId(),
                'request_host' => $_SERVER['HTTP_HOST'],
                'request_url' => $_SERVER['REQUEST_URI'],
                'request_method' => $_SERVER['REQUEST_METHOD'],
                'query_string' => $_SERVER['QUERY_STRING'],
            ],
            $this->additionalData,
        );

        $message = new Message();
        $message
            ->setVersion('1.1')
            ->setHost($host)
            ->setShortMessage($shortMessage)
            ->setFullMessage($fullMessage)
            ->setLevel($logLevel);

        foreach ($data as $key => $value) {
            $message->setAdditional($key, $value);
        }

        try {
            $this->publisher->publish($message);
        } catch (\Exception $ex) {
            // Silence exception
        }
    }

    /**
     * @throws InvalidLogWriterConfigurationException
     */
    private function createPublisher(): Publisher
    {
        $publisher = null;
        if (!empty($this->hostname)) {
            switch ($this->protocol) {
                case 'tcp':
                    $transport = new TcpTransport($this->hostname, $this->port);
                    break;
                case 'udp':
                    $transport = new UdpTransport($this->hostname, $this->port, UdpTransport::CHUNK_SIZE_LAN);
                    break;
                case 'http':
                    $transport = new HttpTransport($this->hostname, $this->port);
                    break;
                default:
                    throw new InvalidLogWriterConfigurationException(
                        "Unknown GELF protocol: \"{$this->protocol}\""
                    );
            }

            $publisher = new Publisher($transport);
        }

        if (is_null($publisher)) {
            $publisher = new Publisher(new UdpTransport());
        }

        return $publisher;
    }

    /**
     * @return string
     */
    public function getHostname(): string
    {
        return $this->hostname;
    }

    /**
     * @param string $hostname
     * @return GelfWriter
     */
    public function setHostname(string $hostname): GelfWriter
    {
        $this->hostname = $hostname;
        return $this;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @param int $port
     * @return GelfWriter
     */
    public function setPort(int $port): GelfWriter
    {
        $this->port = $port;
        return $this;
    }

    /**
     * @return string
     */
    public function getProtocol(): string
    {
        return $this->protocol;
    }

    /**
     * @param string $protocol
     * @return GelfWriter
     */
    public function setProtocol(string $protocol): GelfWriter
    {
        $this->protocol = $protocol;
        return $this;
    }

    /**
     * @return string
     */
    public function getFacility(): string
    {
        return $this->facility;
    }

    /**
     * @param string $facility
     * @return GelfWriter
     */
    public function setFacility(string $facility): GelfWriter
    {
        $this->facility = $facility;
        return $this;
    }

    /**
     * @return array
     */
    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }

    /**
     * @param array $additionalData
     * @return GelfWriter
     */
    public function setAdditionalData(array $additionalData): GelfWriter
    {
        $this->additionalData = $additionalData;
        return $this;
    }
}
