<?php
/**
 * Bounded single-pass content scanner.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Maps post content to top-level segments with byte offsets, without ever rewriting it (docs/adr/004).
 * Total and linear: it never throws, uses an explicit stack (no recursion) and gives up (unsafe) on unterminated
 * markup, depth above 1000, more than 5000 segments, or content above 1 MB. Pure: no WordPress functions.
 */
final class ContentAnalyzer {

	public const MAX_BYTES    = 1048576;
	public const MAX_SEGMENTS = 5000;
	private const MAX_DEPTH   = 1000;

	private const VOID_TAGS  = array( 'br', 'hr', 'img', 'input', 'meta', 'link', 'source', 'wbr', 'embed', 'area', 'base', 'col', 'param', 'track' );
	private const RAW_TAGS   = array( 'script', 'style', 'textarea' );
	private const BLOCK_TAGS = array( 'p', 'div', 'ul', 'ol', 'dl', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'blockquote', 'pre', 'hr', 'figure', 'section', 'article', 'aside', 'header', 'footer', 'nav', 'form', 'fieldset', 'address', 'details', 'main' );

	/**
	 * Scanner state for the current call.
	 *
	 * @var array<string, mixed>
	 */
	private array $st = array();

	/**
	 * Analyzes content.
	 *
	 * @param string $html Post content.
	 */
	public function analyze( string $html ): ContentMap {
		$length = strlen( $html );
		if ( $length > self::MAX_BYTES ) {
			return new ContentMap( array(), $length, true, array() );
		}
		$this->st = array(
			'segments' => array(),
			'ids'      => array(),
			'unsafe'   => false,
			'stop'     => false,
			'elems'    => array(),
			'blocks'   => array(),
			'wraps'    => array(),
			'cnt'      => array(
				'elems'  => array(),
				'blocks' => array(),
				'wraps'  => array(),
			),
			'open'     => false,
			'u'        => array(
				'kind'        => SegmentKind::Other,
				'start'       => 0,
				'weight'      => 0,
				'level'       => null,
				'id'          => null,
				'needs_level' => false,
			),
		);

		$pos = 0;
		while ( $pos < $length && ! $this->st['stop'] ) {
			$tag = $this->nextTagStart( $html, $pos, $length );
			$end = false === $tag ? $length : $tag;
			if ( $end > $pos ) {
				$this->text( $html, $pos, $end );
			}
			if ( false === $tag ) {
				break;
			}
			$pos = $this->markup( $html, $tag, $length );
		}

		if ( ! $this->st['stop'] && $this->st['open'] ) {
			$this->emit( $length );
		}
		return new ContentMap( $this->st['segments'], $length, $this->st['unsafe'], $this->st['ids'] );
	}

	/**
	 * Offset of the next real tag, comment or declaration start at or after $pos, or false.
	 *
	 * @param string $html   Content.
	 * @param int    $pos    Start offset.
	 * @param int    $length Content length.
	 */
	private function nextTagStart( string $html, int $pos, int $length ): int|false {
		$lt = strpos( $html, '<', $pos );
		while ( false !== $lt ) {
			$next = $lt + 1 < $length ? $html[ $lt + 1 ] : '';
			if ( ctype_alpha( $next ) || '!' === $next || '?' === $next || ( '/' === $next && $lt + 2 < $length && ctype_alpha( $html[ $lt + 2 ] ) ) ) {
				return $lt;
			}
			$lt = strpos( $html, '<', $lt + 1 );
		}
		return false;
	}

	/**
	 * Handles a text run between two markup items.
	 *
	 * @param string $html  Content.
	 * @param int    $from  Run start.
	 * @param int    $until Run end (exclusive).
	 */
	private function text( string $html, int $from, int $until ): void {
		$run    = substr( $html, $from, $until - $from );
		$weight = strlen( str_replace( array( ' ', "\t", "\n", "\r", "\f", "\v", '&nbsp;' ), '', $run ) );
		if ( $this->st['open'] ) {
			$this->st['u']['weight'] += $weight;
			return;
		}
		if ( '' === trim( $run ) ) {
			return;
		}
		$lead = strlen( $run ) - strlen( ltrim( $run ) );
		$this->push( SegmentKind::Text, $from + $lead, $from + strlen( rtrim( $run ) ), $weight, null, null );
	}

	/**
	 * Handles one comment, declaration or tag starting at $t. Returns the offset after it.
	 *
	 * @param string $html   Content.
	 * @param int    $t      Start offset.
	 * @param int    $length Content length.
	 */
	private function markup( string $html, int $t, int $length ): int {
		if ( '!' === $html[ $t + 1 ] ) {
			if ( '--' === substr( $html, $t + 2, 2 ) ) {
				$close = strpos( $html, '-->', $t + 4 );
				if ( false === $close ) {
					return $this->giveUp( $length );
				}
				$this->comment( trim( substr( $html, $t + 4, $close - $t - 4 ) ), $t, $close + 3 );
				return $close + 3;
			}
			$needle = '[CDATA[' === substr( $html, $t + 2, 7 ) ? ']]>' : '>';
			$close  = strpos( $html, $needle, $t + 2 );
			return false === $close ? $this->giveUp( $length ) : $close + strlen( $needle );
		}
		if ( '?' === $html[ $t + 1 ] ) {
			$close = strpos( $html, '>', $t + 2 );
			return false === $close ? $this->giveUp( $length ) : $close + 1;
		}
		return '/' === $html[ $t + 1 ] ? $this->closingTag( $html, $t, $length ) : $this->openingTag( $html, $t, $length );
	}

	/**
	 * Marks the content unsafe and stops scanning.
	 *
	 * @param int $length Content length.
	 */
	private function giveUp( int $length ): int {
		$this->st['unsafe'] = true;
		$this->st['stop']   = true;
		$this->st['open']   = false;
		return $length;
	}

	/**
	 * Handles a comment: Gutenberg block delimiters and engine markers.
	 *
	 * @param string $inner Trimmed comment text.
	 * @param int    $start Comment start.
	 * @param int    $end   Comment end (exclusive).
	 */
	private function comment( string $inner, int $start, int $end ): void {
		if ( 1 === preg_match( '/^(\/?)rgp:p(\d{1,12})$/D', $inner, $m ) ) {
			$id = (int) $m[2];
			if ( '' === $m[1] ) {
				$this->st['ids'][ $id ] = $id;
				$this->begin( SegmentKind::Wrapper, $start, null, $id );
				$this->pushName( 'wraps', (string) $id );
				if ( count( $this->st['wraps'] ) > self::MAX_DEPTH ) {
					$this->giveUp( 0 );
				}
				return;
			}
			$this->closeWrapper( $id, $end );
			return;
		}
		if ( 1 === preg_match( '/^wp:([a-z0-9_\/-]+)/', $inner, $m ) ) {
			$name = $m[1];
			$self = str_ends_with( $inner, '/' );
			$kind = $this->blockKind( $name );
			$lvl  = SegmentKind::Heading === $kind ? ( 1 === preg_match( '/"level"\s*:\s*([1-6])/', $inner, $l ) ? (int) $l[1] : null ) : null;
			$was  = $this->depth();
			$this->begin( $kind, $start, $lvl, null, SegmentKind::Heading === $kind && null === $lvl );
			if ( $self ) {
				if ( 0 === $was ) {
					$this->emit( $end );
				}
				return;
			}
			$this->pushName( 'blocks', $name );
			if ( count( $this->st['blocks'] ) > self::MAX_DEPTH ) {
				$this->giveUp( 0 );
			}
			return;
		}
		if ( 1 === preg_match( '/^\/wp:([a-z0-9_\/-]+)$/D', $inner, $m ) ) {
			$this->popName( 'blocks', $m[1] );
			$this->finishIfClosed( $end );
		}
	}

	/**
	 * Handles a closing tag.
	 *
	 * @param string $html   Content.
	 * @param int    $t      Start offset.
	 * @param int    $length Content length.
	 */
	private function closingTag( string $html, int $t, int $length ): int {
		$gt = strpos( $html, '>', $t + 2 );
		if ( false === $gt ) {
			return $this->giveUp( $length );
		}
		$name = strtolower( substr( $html, $t + 2, strcspn( $html, " \t\r\n/>", $t + 2 ) ) );
		$this->popName( 'elems', $name );
		$this->finishIfClosed( $gt + 1 );
		return $gt + 1;
	}

	/**
	 * Handles an opening tag.
	 *
	 * @param string $html   Content.
	 * @param int    $t      Start offset.
	 * @param int    $length Content length.
	 */
	private function openingTag( string $html, int $t, int $length ): int {
		$name_len = strspn( $html, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789:-', $t + 1 );
		$name     = strtolower( substr( $html, $t + 1, $name_len ) );
		$gt       = $this->tagEnd( $html, $t + 1 + $name_len, $length );
		if ( false === $gt ) {
			return $this->giveUp( $length );
		}
		$end = $gt + 1;

		if ( 'p' === ( $this->st['elems'][ count( $this->st['elems'] ) - 1 ] ?? '' ) && in_array( $name, self::BLOCK_TAGS, true ) ) {
			$this->popName( 'elems', 'p' );
			$this->finishIfClosed( $t );
		}

		$was = $this->depth();
		if ( in_array( $name, self::RAW_TAGS, true ) && '/' !== $html[ $gt - 1 ] ) {
			$close = stripos( $html, '</' . $name, $end );
			$cgt   = false === $close ? false : strpos( $html, '>', $close );
			if ( false === $cgt ) {
				return $this->giveUp( $length );
			}
			if ( 0 === $was ) {
				$this->push( SegmentKind::Other, $t, $cgt + 1, 0, null, null );
			}
			return $cgt + 1;
		}
		if ( in_array( $name, self::VOID_TAGS, true ) || '/' === $html[ $gt - 1 ] ) {
			if ( 0 === $was ) {
				$this->push( SegmentKind::Other, $t, $end, 0, null, null );
			}
			return $end;
		}

		$level = 1 === preg_match( '/^h([1-6])$/D', $name, $h ) ? (int) $h[1] : null;
		if ( 0 === $was ) {
			$this->begin( 'p' === $name ? SegmentKind::Paragraph : ( null !== $level ? SegmentKind::Heading : SegmentKind::Other ), $t, $level, null );
		} elseif ( null !== $level && $this->st['u']['needs_level'] ) {
			$this->st['u']['level']       = $level;
			$this->st['u']['needs_level'] = false;
		}
		$this->pushName( 'elems', $name );
		if ( count( $this->st['elems'] ) > self::MAX_DEPTH ) {
			return $this->giveUp( $length );
		}
		return $end;
	}

	/**
	 * Offset of the ">" that ends a tag, honouring quoted attribute values; false when unterminated.
	 *
	 * @param string $html   Content.
	 * @param int    $from   Offset after the tag name.
	 * @param int    $length Content length.
	 */
	private function tagEnd( string $html, int $from, int $length ): int|false {
		$i = $from;
		while ( $i < $length ) {
			$i += strcspn( $html, '>"\'', $i );
			if ( $i >= $length ) {
				return false;
			}
			if ( '>' === $html[ $i ] ) {
				return $i;
			}
			$close = strpos( $html, $html[ $i ], $i + 1 );
			if ( false === $close ) {
				return false;
			}
			$i = $close + 1;
		}
		return false;
	}

	/**
	 * Block kind from a block name.
	 *
	 * @param string $name Block name, with or without the core/ prefix.
	 */
	private function blockKind( string $name ): SegmentKind {
		return match ( str_starts_with( $name, 'core/' ) ? substr( $name, 5 ) : $name ) {
			'paragraph' => SegmentKind::Paragraph,
			'heading'   => SegmentKind::Heading,
			default     => SegmentKind::Other,
		};
	}

	/**
	 * Total nesting depth across elements, blocks and wrappers.
	 */
	private function depth(): int {
		return count( $this->st['elems'] ) + count( $this->st['blocks'] ) + count( $this->st['wraps'] );
	}

	/**
	 * Starts a unit when none is open.
	 *
	 * @param SegmentKind $kind        Kind.
	 * @param int         $start       Start offset.
	 * @param int|null    $level       Heading level.
	 * @param int|null    $id          Wrapper placement id.
	 * @param bool        $needs_level Whether a heading level must still be taken from an inner tag.
	 */
	private function begin( SegmentKind $kind, int $start, ?int $level, ?int $id, bool $needs_level = false ): void {
		if ( $this->st['open'] ) {
			return;
		}
		$this->st['open'] = true;
		$this->st['u']    = array(
			'kind'        => $kind,
			'start'       => $start,
			'weight'      => 0,
			'level'       => $level,
			'id'          => $id,
			'needs_level' => $needs_level,
		);
	}

	/**
	 * Pushes a name onto one of the stacks, keeping a per-name count so stray closers are rejected in O(1).
	 *
	 * @param string $key  Stack key: elems, blocks or wraps.
	 * @param string $name Name.
	 */
	private function pushName( string $key, string $name ): void {
		$this->st[ $key ][]               = $name;
		$this->st['cnt'][ $key ][ $name ] = ( $this->st['cnt'][ $key ][ $name ] ?? 0 ) + 1;
	}

	/**
	 * Closes the nearest open entry with the given name (and everything inside it); a name that is not open is
	 * a stray closer and is ignored.
	 *
	 * @param string $key  Stack key: elems, blocks or wraps.
	 * @param string $name Name.
	 */
	private function popName( string $key, string $name ): void {
		if ( 0 === ( $this->st['cnt'][ $key ][ $name ] ?? 0 ) ) {
			return;
		}
		$stack = &$this->st[ $key ];
		for ( $i = count( $stack ) - 1; $i >= 0; --$i ) {
			if ( $stack[ $i ] === $name ) {
				break;
			}
		}
		foreach ( array_splice( $stack, $i ) as $removed ) {
			--$this->st['cnt'][ $key ][ $removed ];
		}
	}

	/**
	 * Closes a wrapper marker pair.
	 *
	 * @param int $id  Placement id.
	 * @param int $end End offset.
	 */
	private function closeWrapper( int $id, int $end ): void {
		$this->popName( 'wraps', (string) $id );
		$this->finishIfClosed( $end );
	}

	/**
	 * Emits the open unit when everything has closed.
	 *
	 * @param int $end End offset.
	 */
	private function finishIfClosed( int $end ): void {
		if ( $this->st['open'] && 0 === $this->depth() ) {
			$this->emit( $end );
		}
	}

	/**
	 * Emits the open unit ending at $end.
	 *
	 * @param int $end End offset (exclusive).
	 */
	private function emit( int $end ): void {
		$u                = $this->st['u'];
		$this->st['open'] = false;
		$level            = SegmentKind::Heading === $u['kind'] ? ( $u['level'] ?? 2 ) : null;
		$this->push( $u['kind'], $u['start'], $end, $u['weight'], $level, $u['id'] );
	}

	/**
	 * Appends a segment, stopping when the segment limit is reached.
	 *
	 * @param SegmentKind $kind   Kind.
	 * @param int         $start  Start.
	 * @param int         $end    End.
	 * @param int         $weight Weight.
	 * @param int|null    $level  Level.
	 * @param int|null    $id     Placement id.
	 */
	private function push( SegmentKind $kind, int $start, int $end, int $weight, ?int $level, ?int $id ): void {
		if ( count( $this->st['segments'] ) >= self::MAX_SEGMENTS ) {
			$this->st['unsafe'] = true;
			$this->st['stop']   = true;
			return;
		}
		$this->st['segments'][] = new Segment( $kind, $start, $end, $weight, $level, $id );
	}
}
