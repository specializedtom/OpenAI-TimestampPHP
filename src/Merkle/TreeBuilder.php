<?php

namespace OpenTimestamps\Merkle;

use OpenTimestamps\Ops\Op;
use OpenTimestamps\Exception\SerializationException;

class TreeBuilder {
    private array $leaves = [];

    public function addLeaf(string $data): void {
        $this->leaves[] = new TreeNode(hash('sha256', $data, true));
    }

    public function getRoot(): ?string {
        if (empty($this->leaves)) {
            return null;
        }
        $nodes = $this->leaves;
        while (count($nodes) > 1) {
            $nextLevel = [];
            for ($i = 0; $i < count($nodes); $i += 2) {
                $left = $nodes[$i];
                $right = $nodes[$i+1] ?? $left; // duplicate last if odd
                $combinedHash = hash('sha256', $left->hash . $right->hash, true);
                $nextLevel[] = new TreeNode($combinedHash, $left, $right);
            }
            $nodes = $nextLevel;
        }
        return $nodes[0]->hash;
    }

    public function getProof(int $index): array {
        if ($index < 0 || $index >= count($this->leaves)) {
            throw new \InvalidArgumentException('Leaf index out of bounds');
        }
        $proof = [];
        $path = [$this->leaves[$index]];

        $nodes = $this->leaves;
        $idx = $index;

        while (count($nodes) > 1) {
            $nextLevel = [];
            for ($i = 0; $i < count($nodes); $i += 2) {
                $left = $nodes[$i];
                $right = $nodes[$i+1] ?? $left;
                $parentHash = hash('sha256', $left->hash . $right->hash, true);
                $nextLevel[] = new TreeNode($parentHash, $left, $right);
            }
            $siblingIndex = ($idx % 2 === 0) ? $idx + 1 : $idx - 1;
            if ($siblingIndex >= count($nodes)) {
                $siblingIndex = $idx; // duplicate last if odd
            }
            $proof[] = $nodes[$siblingIndex]->hash;
            $idx = intdiv($idx, 2);
            $nodes = $nextLevel;
        }
        return $proof;
    }

    public static function verifyProof(string $leafHash, array $proof, string $rootHash, int $index): bool {
        $hash = $leafHash;
        foreach ($proof as $siblingHash) {
            if ($index % 2 === 0) {
                $hash = hash('sha256', $hash . $siblingHash, true);
            } else {
                $hash = hash('sha256', $siblingHash . $hash, true);
            }
            $index = intdiv($index, 2);
        }
        return $hash === $rootHash;
    }
}