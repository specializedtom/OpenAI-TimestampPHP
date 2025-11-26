<?php

namespace OpenTimestamps\TimestampFile;

use OpenTimestamps\Ops\Op;
use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;
use OpenTimestamps\Serialization\Merkle\TreeBuilder;
use OpenTimestamps\Exception\SerializationException;

class TimestampFile {
    private array $ops = [];
    private int $version = 1;
    private const MAGIC = "OTS";

    public function __construct(array $ops = []) {
        $this->ops = $ops;
    }

    public function addOp(Op $op): void {
        $this->ops[] = $op;
    }

    public function getOps(): array {
        return $this->ops;
    }

    public function serialize(): string {
        $writer = new BinaryWriter();
        $writer->writeByte(ord(self::MAGIC[0]));
        $writer->writeByte(ord(self::MAGIC[1]));
        $writer->writeByte(ord(self::MAGIC[2]));
        $writer->writeByte($this->version);

        $writer->writeVarInt(count($this->ops));
        foreach ($this->ops as $op) {
            $writer->writeVarBytes($op->serialize());
        }
        return $writer->getData();
    }

    public static function deserialize(string $data): self {
        $reader = new BinaryReader($data);

        // magic
        $magic = chr($reader->readByte()) 
            . chr($reader->readByte()) 
            . chr($reader->readByte());
        if ($magic !== self::MAGIC) {
            throw new SerializationException('Invalid magic bytes');
        }

        // version
        $version = $reader->readByte();

        // number of ops
        $count = $reader->readVarInt();

        $ops = [];
        for ($i = 0; $i < $count; $i++) {
            $opData = $reader->readVarBytes();

            // IMPORTANT: use OpFactory so opcode lookup happens correctly
            $ops[] = \OpenTimestamps\Ops\OpFactory::deserialize(
                new BinaryReader($opData)
            );
        }

        $tsFile = new self($ops);
        $tsFile->version = $version;
        return $tsFile;
    }


    public function computeRoot(string $input): string {
        $digest = $input;
        foreach ($this->ops as $op) {
            $digest = $op->apply($digest);
        }
        return $digest;
    }

    public function buildMerkleTree(string $input): TreeBuilder {
        $tree = new TreeBuilder();
        $leafHash = $this->computeRoot($input);
        $tree->addLeaf($leafHash);

        // Add additional leaves from CalendarCommitOps if present
        foreach ($this->ops as $op) {
            if ($op instanceof \OpenTimestamps\Ops\CalendarCommitOp) {
                $tree->addLeaf($op->apply($leafHash));
            }
        }

        $tree->build();
        return $tree;
    }

    public function getMerkleRoot(string $input): string {
        return $this->buildMerkleTree($input)->getRoot();
    }

    public function verifyBitcoin(string $leafHash, array $merkleProof = []): bool
    {
        $opReturn = null;
        $blockOp = null;

        foreach ($this->ops as $op) {
            if ($op instanceof OpReturnOp) {
                $opReturn = $op;
            } elseif ($op instanceof BitcoinBlockHeaderOp) {
                $blockOp = $op;
            }
        }

        if (!$opReturn || !$blockOp) {
            throw new \RuntimeException('No Bitcoin attestation found in TimestampFile.');
        }

        return \OpenTimestamps\Verification\BitcoinVerifier::verifyCommitment(
            $leafHash,
            $opReturn,
            $blockOp,
            $merkleProof
        );
    }

}
