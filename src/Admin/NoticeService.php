<?php

namespace ClientAccessPortalGoogleDrive\Admin;

class NoticeService {
	private string $transient_prefix = 'client_access_portal_google_drive_notice_';

	public function success( string $message ): void {
		$this->store( 'success', $message );
	}

	public function error( string $message ): void {
		$this->store( 'error', $message );
	}

	public function render(): void {
		if ( ! is_admin() || ! is_user_logged_in() ) {
			return;
		}

		$notice = get_transient( $this->transient_key() );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $this->transient_key() );
		$css_class = 'success' === $notice['type'] ? 'notice-success' : 'notice-error';
		?>
		<div class="notice <?php echo esc_attr( $css_class ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
		<?php
	}

	private function store( string $type, string $message ): void {
		set_transient(
			$this->transient_key(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
	}

	private function transient_key(): string {
		return $this->transient_prefix . get_current_user_id();
	}
}
