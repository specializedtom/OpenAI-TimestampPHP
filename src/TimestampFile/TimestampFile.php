<?php

namespace OpenTimestamps\TimestampFile;

use OpenTimestamps\Op\Op;
use OpenTimestamps\Op\OpFactory;
use OpenTimestamps\Op\CalendarCommitOp;
use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;
use OpenTimestamps\Serialization\Merkle\TreeBuilder;
use OpenTimestamps\Exception\SerializationException;

class TimestampFile
{
    private array $ops = [];
    private int $version = 1;
    private ?string $initialDigest = null; // 32-byte binary or null
    private const MAGIC = "OTS";

    public function __construct(array $ops = [], ?string $initialDigest = null)
    {
        $this->ops = $ops;
        if ($initialDigest !== null) {
            if (strlen($initialDigest) !== 32) {
                throw new SerializationException('Initial digest must be 32 bytes (SHA-256)');
            }
        }
        $this->initialDigest = $initialDigest;
    }

    /**
     * Construct a TimestampFile that starts from a 32-byte digest (detached).
     */
    public static function fromDigest(string $digest): self
    {
        if (strlen($digest) !== 32) {
            throw new SerializationException('Digest must be 32 bytes');
        }
        return new self([], $digest);
    }

    public function addOp(Op $op): void
    {
        $this->ops[] = $op;
    }

    /**
     * Return op list (shallow copy)
     */
    public function getOps(): array
    {
        return $this->ops;
    }

    /**
     * Returns the stored initial digest (32-byte binary) or null.
     */
    public function getInitialDigest(): ?string
    {
        return $this->initialDigest;
    }

    /**
     * Set initial digest (32-byte binary)
     */
    public function setInitialDigest(string $digest): void
    {
        if (strlen($digest) !== 32) {
            throw new SerializationException('Initial digest must be 32 bytes (SHA-256)');
        }
        $this->initialDigest = $digest;
    }

    public function serialize(): string
    {
        $writer = new BinaryWriter();
        $writer->writeByte(ord(self::MAGIC[0]));
        $writer->writeByte(ord(self::MAGIC[1]));
        $writer->writeByte(ord(self::MAGIC[2]));
        $writer->writeByte($this->version);

        // Persist the initial digest as varbytes (empty if null)
        $writer->writeVarBytes($this->initialDigest ?? "");

        // Number of ops
        $writer->writeVarInt(count($this->ops));
        foreach ($this->ops as $op) {
            $writer->writeVarBytes($op->serialize());
        }

        return $writer->getData();
    }

    public static function deserialize(string $data): self
    {
        $reader = new BinaryReader($data);
        $magic = chr($reader->readByte()) . chr($reader->readByte()) . chr($reader->readByte());
        if ($magic !== self::MAGIC) {
            throw new SerializationException('Invalid magic bytes');
        }

        $version = $reader->readByte();

        // Read initial digest (may be empty)
        $initialDigest = $reader->readVarBytes();
        if ($initialDigest === "") {
            $initialDigest = null;
        } elseif (strlen($initialDigest) !== 32) {
            throw new SerializationException('Stored initial digest must be 32 bytes or empty');
        }

        $count = $reader->readVarInt();

        $ops = [];
        for ($i = 0; $i < $count; $i++) {
            $opData = $reader->readVarBytes();
            $opReader = new BinaryReader($opData);
            $ops[] = OpFactory::deserialize($opReader);
        }

        $tsFile = new self($ops, $initialDigest);
        $tsFile->version = $version;
        return $tsFile;
    }

    /**
     * Apply ops to given 32-byte digest and return resulting digest string.
     * The provided $fileDigest must be 32 bytes.
     */
    public function computeRoot(string $fileDigest): string
    {
        if (strlen($fileDigest) !== 32) {
            throw new SerializationException('computeRoot requires a 32-byte file digest');
        }

        $digest = $fileDigest;

        // Apply ops in order (excluding file-hash op if present)
        foreach ($this->ops as $op) {
            // Some ops may expect raw bytes; in typical OTS the first op
            // applied to detached digest is further hashing ops. This method
            // assumes $fileDigest is the correct starting point.
            $digest = $op->apply($digest);
        }

        return $digest;
    }

    /**
     * Build a merkle tree if there are calendar commit ops.
     * Returns TreeBuilder or null if no commit ops present.
     */
    public function buildMerkleTree(string $inputDigest): ?TreeBuilder
    {
        $commitOps = array_filter($this->ops, fn($op) => $op instanceof CalendarCommitOp);
        if (empty($commitOps)) {
            return null;
        }

        $leaf = $this->computeRoot($inputDigest);
        $tree = new TreeBuilder();
        $tree->addLeaf($leaf);

        foreach ($commitOps as $op) {
            // CalendarCommitOp::apply should return the attestation-derived 32-byte value
            $tree->addLeaf($op->apply($leaf));
        }

        $tree->build();
        return $tree;
    }

    /**
     * Get the merkle root for this timestamp file.
     * Requires a 32-byte input digest (or uses stored initialDigest if present).
     */
    public function getMerkleRoot(string $inputDigest = ""): string
    {
        if ($this->initialDigest !== null) {
            $inputDigest = $this->initialDigest;
        }

        if (strlen($inputDigest) !== 32) {
            throw new SerializationException(
                'Input to getMerkleRoot must be a 32-byte SHA-256 digest'
            );
        }

        $tree = $this->buildMerkleTree($inputDigest);
        return $tree ? $tree->getRoot() : $this->computeRoot($inputDigest);
    }

    /**
     * Compute the leaf hash from the ops WITHOUT requiring an external digest.
     * This reads ops in order and applies them starting from an empty string.
     * If result is not 32 bytes, throws. This is useful for legacy .ots files
     * where the ops encode a means to produce the 32-byte digest (e.g. sha256-file op).
     */
    public function computeLeafHash(): string
    {
        $hash = '';
        foreach ($this->ops as $op) {
            $hash = $op->apply($hash);
        }

        if (strlen($hash) !== 32) {
            throw new SerializationException('Leaf hash must be a 32-byte SHA-256 digest.');
        }

        return $hash;
    }
}
