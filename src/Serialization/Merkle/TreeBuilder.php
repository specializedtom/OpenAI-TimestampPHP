<?php

namespace OpenTimestamps\Serialization\Merkle;

class TreeBuilder {
    private array $leaves = [];
    private ?TreeNode $root = null;

    public function addLeaf(string $hash): void {
        $this->leaves[] = new TreeNode($hash);
    }

    public function build(): void {
        $nodes = $this->leaves;
        while (count($nodes) > 1) {
            $newLevel = [];
            for ($i = 0; $i < count($nodes); $i += 2) {
                $left = $nodes[$i];
                $right = $nodes[$i + 1] ?? $nodes[$i]; // duplicate last if odd
                $combined = hash('sha256', $left->hash . $right->hash, true);
                $newLevel[] = new TreeNode($combined, $left, $right);
            }
            $nodes = $newLevel;
        }
        $this->root = $nodes[0] ?? null;
    }

    public function getRoot(): ?string {
        if ($this->root === null) {
            $this->build();
        }
        return $this->root?->hash;
    }

    public function getProof(int $index): array {
        $proof = [];
        $nodes = $this->leaves;
        if ($index < 0 || $index >= count($nodes)) {
            throw new \OutOfBoundsException('Leaf index out of bounds');
        }

        while (count($nodes) > 1) {
            $newLevel = [];
            for ($i = 0; $i < count($nodes); $i += 2) {
                $left = $nodes[$i];
                $right = $nodes[$i + 1] ?? $nodes[$i];
                $combined = hash('sha256', $left->hash . $right->hash, true);
                $newLevel[] = new TreeNode($combined, $left, $right);

                // If our target index is in this pair, add sibling to proof
                if ($i === $index || $i + 1 === $index) {
                    $sibling = ($i === $index) ? $right->hash : $left->hash;
                    $proof[] = $sibling;
                    $index = count($newLevel) - 1; // move index up to parent
                }
            }
            $nodes = $newLevel;
        }
        return $proof;
    }
}
