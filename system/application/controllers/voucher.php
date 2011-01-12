<?php

class Voucher extends Controller {

	function Voucher()
	{
		parent::Controller();
		$this->load->model('Voucher_model');
		$this->load->model('Ledger_model');
		$this->load->model('Tag_model');
		return;
	}

	function index()
	{
		redirect('voucher/show/all');
		return;
	}

	function show($voucher_type)
	{
		$this->load->model('Tag_model');
		$data['tag_id'] = 0;
		if ($voucher_type == "tag")
		{
			$tag_id = (int)$this->uri->segment(4);
			$data['tag_id'] = $tag_id;
		}

		switch ($voucher_type)
		{
		case 'all' :
			$this->template->set('page_title', 'All Vouchers');
			$data['voucher_type'] = "";
			break;
		case 'receipt' :
			$this->template->set('page_title', 'Receipt Vouchers');
			$this->template->set('nav_links', array('voucher/add/receipt' => 'New Receipt Voucher'));
			$data['voucher_type'] = "receipt";
			break;
		case 'payment' :
			$this->template->set('page_title', 'Payment Vouchers');
			$this->template->set('nav_links', array('voucher/add/payment' => 'New Payment Voucher'));
			$data['voucher_type'] = "payment";
			break;
		case 'contra' :
			$this->template->set('page_title', 'Contra Vouchers');
			$this->template->set('nav_links', array('voucher/add/contra' => 'New Contra Voucher'));
			$data['voucher_type'] = "contra";
			break;
		case 'journal' :
			$this->template->set('page_title', 'Journal Vouchers');
			$this->template->set('nav_links', array('voucher/add/journal' => 'New Journal Voucher'));
			$data['voucher_type'] = "journal";
			break;
		case 'tag' :
			$tag_name = $this->Tag_model->tag_name($tag_id);
			$this->template->set('page_title', 'Vouchers Tagged "' . $tag_name . '"');
			$data['voucher_type'] = "";
			break;
		default :
			$this->messages->add('Invalid voucher type(1).', 'error');
			redirect('voucher/show/all');
			return;
			break;
		}

		$voucher_type_int = v_to_n($voucher_type);

		$voucher_q = NULL;

		/* Pagination setup */
		$this->load->library('pagination');

		if ($voucher_type == "tag")
			$page_count = (int)$this->uri->segment(5);
		else
			$page_count = (int)$this->uri->segment(4);

		$page_count = $this->input->xss_clean($page_count);
		if ( ! $page_count)
			$page_count = "0";

		/* Pagination configuration */
		if ($voucher_type == "tag")
		{
			$config['base_url'] = site_url('voucher/show/' . $voucher_type. "/" . $tag_id);
			$config['uri_segment'] = 5;
		} else {
			$config['base_url'] = site_url('voucher/show/' . $voucher_type);
			$config['uri_segment'] = 4;
		}
		$pagination_counter = $this->config->item('row_count');
		$config['num_links'] = 10;
		$config['per_page'] = $pagination_counter;
		$config['full_tag_open'] = '<ul id="pagination-flickr">';
		$config['full_close_open'] = '</ul>';
		$config['num_tag_open'] = '<li>';
		$config['num_tag_close'] = '</li>';
		$config['cur_tag_open'] = '<li class="active">';
		$config['cur_tag_close'] = '</li>';
		$config['next_link'] = 'Next &#187;';
		$config['next_tag_open'] = '<li class="next">';
		$config['next_tag_close'] = '</li>';
		$config['prev_link'] = '&#171; Previous';
		$config['prev_tag_open'] = '<li class="previous">';
		$config['prev_tag_close'] = '</li>';
		$config['first_link'] = 'First';
		$config['first_tag_open'] = '<li class="first">';
		$config['first_tag_close'] = '</li>';
		$config['last_link'] = 'Last';
		$config['last_tag_open'] = '<li class="last">';
		$config['last_tag_close'] = '</li>';

		if (($voucher_type_int < 0) || ($voucher_type_int > 4))
		{
			$this->messages->add('Invalid voucher type(2).', 'error');
			redirect('voucher/show/all');
			return;
		} else if ($voucher_type == "tag") {
			$voucher_q = $this->db->query("SELECT * FROM vouchers WHERE tag_id = ? ORDER BY date DESC, number DESC LIMIT ${page_count}, ${pagination_counter}", array($tag_id));
			$config['total_rows'] = $this->db->query("SELECT * FROM vouchers WHERE tag_id = ?", array($tag_id))->num_rows();
		} else if ($voucher_type_int > 0) {
			$voucher_q = $this->db->query("SELECT * FROM vouchers WHERE type = ? ORDER BY date DESC, number DESC LIMIT ${page_count}, ${pagination_counter}", array($voucher_type_int));
			$config['total_rows'] = $this->db->query("SELECT * FROM vouchers WHERE type = ?", array($voucher_type_int))->num_rows();
		} else {
			$voucher_q = $this->db->query("SELECT * FROM vouchers ORDER BY date DESC, number DESC LIMIT ${page_count}, ${pagination_counter}");
			$config['total_rows'] = $this->db->count_all('vouchers');
		}

		/* Pagination initializing */
		$this->pagination->initialize($config);

		$html = "<table border=0 cellpadding=5 class=\"simple-table\">";
		$html .= "<thead><tr><th>Date</th><th>No</th><th>Ledger A/C</th><th>Type</th><th>DR Amount</th><th>CR Amount</th><th></th></tr></thead>";
		$html .= "<tbody>";

		$odd_even = "odd";
		foreach ($voucher_q->result() as $row)
		{
			$html_voucher_type = n_to_v($row->type);

			/* Showing the credit ledger name for payment and debit for other voucher types */
			$ledger_type = ($row->type == 2) ? "C" : "D";
			$this->db->select('ledgers.name as name');
			$this->db->from('voucher_items')->join('ledgers', 'voucher_items.ledger_id = ledgers.id')->where('voucher_items.voucher_id', $row->id)->where('voucher_items.dc', $ledger_type);
			$ledger_q = $this->db->get();
			$ledger_multiple = ($ledger_q->num_rows() > 1) ? TRUE : FALSE;
			$ledger = $ledger_q->row();

			$html .= "<tr class=\"tr-" . $odd_even . "\">";

			$html .= "<td>" . date_mysql_to_php_display($row->date) . "</td>";
			$html .= "<td>" . anchor('voucher/view/' . strtolower($html_voucher_type) . "/" . $row->id, voucher_number_prefix($html_voucher_type) . $row->number, array('title' => 'View ' . ucfirst($html_voucher_type) . ' Voucher', 'class' => 'anchor-link-a')) . "</td>";

			$html .= "<td>";
			$html .= $this->Tag_model->show_voucher_tag($row->tag_id);
			if ($ledger)
				if ($ledger_multiple)
					$html .= anchor('voucher/view/' . strtolower($html_voucher_type) . "/" . $row->id, "(" . $ledger->name . ")", array('title' => 'View ' . ucfirst($html_voucher_type) . ' Voucher', 'class' => 'anchor-link-a'));
				else
					$html .= anchor('voucher/view/' . strtolower($html_voucher_type) . "/" . $row->id, $ledger->name, array('title' => 'View ' . ucfirst($html_voucher_type) . ' Voucher', 'class' => 'anchor-link-a'));
			$html .= "</td>";

			$html .= "<td>" . ucfirst($html_voucher_type) . "</td>";
			$html .= "<td>" . $row->dr_total . "</td>";
			$html .= "<td>" . $row->cr_total . "</td>";

			$html .= "<td>" . anchor('voucher/edit/' . strtolower($html_voucher_type) . "/" . $row->id , "Edit", array('title' => 'Edit ' . ucfirst($html_voucher_type) . ' Voucher', 'class' => 'red-link')) . " ";

			$html .= " &nbsp;" . anchor('voucher/delete/' . strtolower($html_voucher_type) . "/" . $row->id , img(array('src' => asset_url() . "images/icons/delete.png", 'border' => '0', 'alt' => 'Delete ' . ucfirst($html_voucher_type) . ' Voucher', 'class' => "confirmClick", 'title' => "Delete voucher")), array('title' => 'Delete  ' . ucfirst($html_voucher_type) . ' Voucher')) . " ";

			$html .= " &nbsp;" . anchor_popup('voucher/printpreview/' . strtolower($html_voucher_type) . "/" . $row->id , img(array('src' => asset_url() . "images/icons/print.png", 'border' => '0', 'alt' => 'Print ' . ucfirst($html_voucher_type) . ' Voucher')), array('title' => 'Print ' . ucfirst($html_voucher_type) . ' Voucher', 'width' => '600', 'height' => '600')) . " ";

			$html .= " &nbsp;" . anchor_popup('voucher/email/' . strtolower($html_voucher_type) . "/" . $row->id , img(array('src' => asset_url() . "images/icons/email.png", 'border' => '0', 'alt' => 'Email ' . ucfirst($html_voucher_type) . ' Voucher')), array('title' => 'Email ' . ucfirst($html_voucher_type) . ' Voucher', 'width' => '500', 'height' => '300')) . " ";

			$html .= " &nbsp;" . anchor('voucher/download/' . strtolower($html_voucher_type) . "/" . $row->id , img(array('src' => asset_url() . "images/icons/save.png", 'border' => '0', 'alt' => 'Download ' . ucfirst($html_voucher_type) . ' Voucher', 'title' => "Download voucher")), array('title' => 'Download  ' . ucfirst($html_voucher_type) . ' Voucher')) . "</td>";

			$html .= "</tr>";
			$odd_even = ($odd_even == "odd") ? "even" : "odd";
		}
		$html .= "</tbody>";
		$html .= "</table>";
		$data['voucher_table'] = $html;

		$this->template->load('template', 'voucher/index', $data);
		return;

	}

	function view($voucher_type, $voucher_id = 0)
	{
		switch ($voucher_type)
		{
		case 'receipt' :
			$this->template->set('page_title', 'View Receipt Voucher');
			break;
		case 'payment' :
			$this->template->set('page_title', 'View Payment Voucher');
			break;
		case 'contra' :
			$this->template->set('page_title', 'View Contra Voucher');
			break;
		case 'journal' :
			$this->template->set('page_title', 'View Journal Voucher');
			break;
		default :
			$this->messages->add('Invalid voucher type(3).', 'error');
			redirect('voucher/show/all');
			return;
			break;
		}

		/* Load current voucher details */
		if ( ! $cur_voucher = $this->Voucher_model->get_voucher($voucher_id, $voucher_type))
		{
			$this->messages->add('Invalid Voucher number.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}
		/* Load current voucher details */
		$this->db->from('voucher_items')->where('voucher_id', $voucher_id)->order_by('id', 'asc');
		$cur_voucher_ledgers = $this->db->get();
		if ($cur_voucher_ledgers->num_rows() < 1)
		{
			$this->messages->add('Voucher has no associated ledger data.', 'error');
		}
		$data['cur_voucher'] = $cur_voucher;
		$data['cur_voucher_ledgers'] = $cur_voucher_ledgers;
		$data['voucher_type'] = $voucher_type;
		$this->template->load('template', 'voucher/view', $data);
		return;
	}

	function add($voucher_type)
	{
		/* Check access */
		if ( ! check_access('create voucher'))
		{
			$this->messages->add('Permission denied.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}

		/* Check for account lock */
		if ($this->config->item('account_locked') == 1)
		{
			$this->messages->add('Account is locked.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}

		switch ($voucher_type)
		{
		case 'receipt' :
			$this->template->set('page_title', 'New Receipt Voucher');
			break;
		case 'payment' :
			$this->template->set('page_title', 'New Payment Voucher');
			break;
		case 'contra' :
			$this->template->set('page_title', 'New Contra Voucher');
			break;
		case 'journal' :
			$this->template->set('page_title', 'New Journal Voucher');
			break;
		default :
			$this->messages->add('Invalid voucher type(4).', 'error');
			redirect('voucher/show/all');
			return;
			break;
		}

		/* Form fields */
		$data['voucher_number'] = array(
			'name' => 'voucher_number',
			'id' => 'voucher_number',
			'maxlength' => '11',
			'size' => '11',
			'value' => '',
		);
		$data['voucher_date'] = array(
			'name' => 'voucher_date',
			'id' => 'voucher_date',
			'maxlength' => '11',
			'size' => '11',
			'value' => date_today_php(),
		);
		$data['voucher_narration'] = array(
			'name' => 'voucher_narration',
			'id' => 'voucher_narration',
			'cols' => '50',
			'rows' => '4',
			'value' => '',
		);
		$data['voucher_type'] = $voucher_type;
		$data['voucher_print'] = FALSE;
		$data['voucher_email'] = FALSE;
		$data['voucher_download'] = FALSE;
		$data['voucher_tags'] = $this->Tag_model->get_all_tags();
		$data['voucher_tag'] = 0;

		/* Form validations */
		$this->form_validation->set_rules('voucher_number', 'Voucher Number', 'trim|is_natural_no_zero|uniquevoucherno[' . v_to_n($voucher_type) . ']');
		$this->form_validation->set_rules('voucher_date', 'Voucher Date', 'trim|required|is_date|is_date_within_range');
		$this->form_validation->set_rules('voucher_narration', 'trim');
		$this->form_validation->set_rules('voucher_tag', 'Tag', 'trim|is_natural');

		/* Debit and Credit amount validation */
		if ($_POST)
		{
			foreach ($this->input->post('ledger_dc', TRUE) as $id => $ledger_data)
			{
				$this->form_validation->set_rules('dr_amount[' . $id . ']', 'Debit Amount', 'trim|currency');
				$this->form_validation->set_rules('cr_amount[' . $id . ']', 'Credit Amount', 'trim|currency');
			}
		}

		/* Repopulating form */
		if ($_POST)
		{
			$data['voucher_number']['value'] = $this->input->post('voucher_number', TRUE);
			$data['voucher_date']['value'] = $this->input->post('voucher_date', TRUE);
			$data['voucher_narration']['value'] = $this->input->post('voucher_narration', TRUE);
			$data['voucher_print'] = $this->input->post('voucher_print', TRUE);
			$data['voucher_email'] = $this->input->post('voucher_email', TRUE);
			$data['voucher_download'] = $this->input->post('voucher_download', TRUE);
			$data['voucher_tag'] = $this->input->post('voucher_tag', TRUE);

			$data['ledger_dc'] = $this->input->post('ledger_dc', TRUE);
			$data['ledger_id'] = $this->input->post('ledger_id', TRUE);
			$data['dr_amount'] = $this->input->post('dr_amount', TRUE);
			$data['cr_amount'] = $this->input->post('cr_amount', TRUE);
		} else {
			for ($count = 0; $count <= 5; $count++)
			{
				if ($count == 0 && $voucher_type == "payment")
					$data['ledger_dc'][$count] = "C";
				else if ($count == 1 && $voucher_type != "payment")
					$data['ledger_dc'][$count] = "C";
				else
					$data['ledger_dc'][$count] = "D";
				$data['ledger_id'][$count] = 0;
				$data['dr_amount'][$count] = "";
				$data['cr_amount'][$count] = "";
			}
		}

		if ($this->form_validation->run() == FALSE)
		{
			$this->messages->add(validation_errors(), 'error');
			$this->template->load('template', 'voucher/add', $data);
			return;
		}
		else
		{
			/* Checking for Valid Ledgers A/C and Debit and Credit Total */
			$data_all_ledger_id = $this->input->post('ledger_id', TRUE);
			$data_all_ledger_dc = $this->input->post('ledger_dc', TRUE);
			$data_all_dr_amount = $this->input->post('dr_amount', TRUE);
			$data_all_cr_amount = $this->input->post('cr_amount', TRUE);
			$dr_total = 0;
			$cr_total = 0;
			$bank_cash_present = FALSE; /* Whether atleast one Ledger A/C is Bank or Cash A/C */
			foreach ($data_all_ledger_dc as $id => $ledger_data)
			{
				if ($data_all_ledger_id[$id] < 1)
					continue;

				/* Check for valid ledger id */
				$this->db->from('ledgers')->where('id', $data_all_ledger_id[$id]);
				$valid_ledger_q = $this->db->get();
				if ($valid_ledger_q->num_rows() < 1)
				{
					$this->messages->add('Invalid Ledger A/C.', 'error');
					$this->template->load('template', 'voucher/add', $data);
					return;
				} else {
					/* Check for valid ledger type */
					$valid_ledger = $valid_ledger_q->row();
					if ($voucher_type == 'receipt')
					{
						if ($data_all_ledger_dc[$id] == 'D' && $valid_ledger->type == 'B')
						{
							$bank_cash_present = TRUE;
						}
					} else if ($voucher_type == 'payment')
					{
						if ($data_all_ledger_dc[$id] == 'C' && $valid_ledger->type == 'B')
						{
							$bank_cash_present = TRUE;
						}
					} else if ($voucher_type == 'contra')
					{
						if ($valid_ledger->type != 'B')
						{
							$this->messages->add('Invalid Ledger A/C. Contra Vouchers can have only Bank and Cash Ledgers A/C\'s.', 'error');
							$this->template->load('template', 'voucher/add', $data);
							return;
						}
					} else if ($voucher_type == 'journal')
					{
						if ($valid_ledger->type == 'B')
						{
							$this->messages->add('Invalid Ledger A/C. Journal Vouchers cannot have Bank and Cash Ledgers A/C\'s.', 'error');
							$this->template->load('template', 'voucher/add', $data);
							return;
						}
					}
				}

				if ($data_all_ledger_dc[$id] == "D")
				{
					$dr_total += $data_all_dr_amount[$id];
				} else {
					$cr_total += $data_all_cr_amount[$id];
				}
			}
			if ($dr_total != $cr_total)
			{
				$this->messages->add('Debit and Credit Total does not match!', 'error');
				$this->template->load('template', 'voucher/add', $data);
				return;
			} else if ($dr_total == 0 && $cr_total == 0) {
				$this->messages->add('Cannot save empty voucher.', 'error');
				$this->template->load('template', 'voucher/add', $data);
				return;
			}
			/* Check if atleast one Bank or Cash Ledger A/C is present */
			if ($voucher_type == 'receipt')
			{
				if ( ! $bank_cash_present)
				{
					$this->messages->add('Need to Debit atleast one Bank or Cash A/C', 'error');
					$this->template->load('template', 'voucher/add', $data);
					return;
				}
			} else if ($voucher_type == 'payment')
			{
				if ( ! $bank_cash_present)
				{
					$this->messages->add('Need to Credit atleast one Bank or Cash A/C', 'error');
					$this->template->load('template', 'voucher/add', $data);
					return;
				}
			}

			/* Adding main voucher */
			if ($this->input->post('voucher_number', TRUE))
				$data_number = $this->input->post('voucher_number', TRUE);
			else
				$data_number = $this->Voucher_model->next_voucher_number($voucher_type);

			$data_date = $this->input->post('voucher_date', TRUE);
			$data_narration = $this->input->post('voucher_narration', TRUE);
			$data_tag = $this->input->post('voucher_tag', TRUE);
			$data_type = 0;
			switch ($voucher_type)
			{
				case "receipt": $data_type = 1; break;
				case "payment": $data_type = 2; break;
				case "contra": $data_type = 3; break;
				case "journal": $data_type = 4; break;
			}
			$data_date = date_php_to_mysql($data_date); // Converting date to MySQL
			$voucher_id = NULL;

			$this->db->trans_start();
			$insert_data = array(
				'number' => $data_number,
				'date' => $data_date,
				'narration' => $data_narration,
				'type' => $data_type,
				'tag_id' => $data_tag,
			);
			if ( ! $this->db->insert('vouchers', $insert_data))
			{
				$this->db->trans_rollback();
				$this->messages->add('Error addding Voucher.', 'error');
				$this->logger->write_message("error", "Error adding " . ucfirst($voucher_type) . " Voucher number " . voucher_number_prefix($voucher_type) . $data_number . " since failed inserting voucher");
				$this->template->load('template', 'voucher/add', $data);
				return;
			} else {
				$voucher_id = $this->db->insert_id();
			}

			/* Adding ledger accounts */
			$data_all_ledger_dc = $this->input->post('ledger_dc', TRUE);
			$data_all_ledger_id = $this->input->post('ledger_id', TRUE);
			$data_all_dr_amount = $this->input->post('dr_amount', TRUE);
			$data_all_cr_amount = $this->input->post('cr_amount', TRUE);

			$dr_total = 0;
			$cr_total = 0;
			foreach ($data_all_ledger_dc as $id => $ledger_data)
			{
				$data_ledger_dc = $data_all_ledger_dc[$id];
				$data_ledger_id = $data_all_ledger_id[$id];
				if ($data_ledger_id < 1)
					continue;
				$data_amount = 0;
				if ($data_all_ledger_dc[$id] == "D")
				{
					$data_amount = $data_all_dr_amount[$id];
					$dr_total += $data_all_dr_amount[$id];
				} else {
					$data_amount = $data_all_cr_amount[$id];
					$cr_total += $data_all_cr_amount[$id];
				}
				$insert_ledger_data = array(
					'voucher_id' => $voucher_id,
					'ledger_id' => $data_ledger_id,
					'amount' => $data_amount,
					'dc' => $data_ledger_dc,
				);
				if ( ! $this->db->insert('voucher_items', $insert_ledger_data))
				{
					$this->db->trans_rollback();
					$this->messages->add('Error addding Ledger A/C ' . $data_ledger_id . '.', 'error');
					$this->logger->write_message("error", "Error adding " . ucfirst($voucher_type) . " Voucher number " . voucher_number_prefix($voucher_type) . $data_number . " since failed inserting voucher ledger items " . "[id:" . $data_ledger_id . "]");
					$this->template->load('template', 'voucher/add', $data);
					return;
				}
			}

			/* Updating Debit and Credit Total in vouchers table */
			$update_data = array(
				'dr_total' => $dr_total,
				'cr_total' => $cr_total,
			);
			if ( ! $this->db->where('id', $voucher_id)->update('vouchers', $update_data))
			{
				$this->db->trans_rollback();
				$this->messages->add('Error updating voucher total.', 'error');
				$this->logger->write_message("error", "Error adding " . ucfirst($voucher_type) . " Voucher number " . voucher_number_prefix($voucher_type) . $data_number . " since failed updating debit and credit total");
				$this->template->load('template', 'voucher/add', $data);
				return;
			}

			/* Success */
			$this->db->trans_complete();

			/* Check for Voucher Print, Download, Email */
			if ($this->input->post('voucher_print', TRUE))
			{
				$this->session->set_userdata('print_voucher', TRUE);
				$this->session->set_userdata('print_voucher_type', strtolower($voucher_type));
				$this->session->set_userdata('print_voucher_id', $voucher_id);
			}
			if ($this->input->post('voucher_email', TRUE))
			{
				$this->session->set_userdata('email_voucher', TRUE);
				$this->session->set_userdata('email_voucher_type', strtolower($voucher_type));
				$this->session->set_userdata('email_voucher_id', $voucher_id);
			}
			if ($this->input->post('voucher_download', TRUE))
			{
				$this->session->set_userdata('download_voucher', TRUE);
				$this->session->set_userdata('download_voucher_type', strtolower($voucher_type));
				$this->session->set_userdata('download_voucher_id', $voucher_id);
			}

			/* Voucher Actions */
			$voucher_success_links = "You can ";
			$voucher_success_links .= anchor('voucher/view/' . strtolower($voucher_type) . "/" . $voucher_id, 'View', array('class' => 'anchor-link-a')) . " or ";
			$voucher_success_links .= anchor('voucher/download/' . strtolower($voucher_type) . "/" . $voucher_id, 'Download', array('class' => 'anchor-link-a'));
			$voucher_success_links .= " it.";

			$this->messages->add('Added ' . ucfirst($voucher_type) . ' Voucher number ' . voucher_number_prefix($voucher_type) . $data_number . ". " . $voucher_success_links, 'success');
			$this->logger->write_message("success", "Added " . ucfirst($voucher_type) . " Voucher number " . voucher_number_prefix($voucher_type) . $data_number . " [id:" . $voucher_id . "]");
			redirect('voucher/show/' . $voucher_type);
			$this->template->load('template', 'voucher/add', $data);
			return;
		}
		return;
	}

	function edit($voucher_type, $voucher_id = 0)
	{
		/* Check access */
		if ( ! check_access('edit voucher'))
		{
			$this->messages->add('Permission denied.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}

		/* Check for account lock */
		if ($this->config->item('account_locked') == 1)
		{
			$this->messages->add('Account is locked.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}

		switch ($voucher_type)
		{
		case 'receipt' :
			$this->template->set('page_title', 'Edit Receipt Voucher');
			break;
		case 'payment' :
			$this->template->set('page_title', 'Edit Payment Voucher');
			break;
		case 'contra' :
			$this->template->set('page_title', 'Edit Contra Voucher');
			break;
		case 'journal' :
			$this->template->set('page_title', 'Edit Journal Voucher');
			break;
		default :
			$this->messages->add('Invalid voucher type(5).', 'error');
			redirect('voucher/show/all');
			return;
			break;
		}

		/* Load current voucher details */
		if ( ! $cur_voucher = $this->Voucher_model->get_voucher($voucher_id, $voucher_type))
		{
			$this->messages->add('Invalid Voucher number.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}

		/* Form fields - Voucher */
		$data['voucher_number'] = array(
			'name' => 'voucher_number',
			'id' => 'voucher_number',
			'maxlength' => '11',
			'size' => '11',
			'value' => $cur_voucher->number,
		);
		$data['voucher_date'] = array(
			'name' => 'voucher_date',
			'id' => 'voucher_date',
			'maxlength' => '11',
			'size' => '11',
			'value' => date_mysql_to_php($cur_voucher->date),
		);
		$data['voucher_narration'] = array(
			'name' => 'voucher_narration',
			'id' => 'voucher_narration',
			'cols' => '50',
			'rows' => '4',
			'value' => $cur_voucher->narration,
		);
		$data['voucher_type'] = $voucher_type;
		$data['voucher_id'] = $voucher_id;
		$data['voucher_print'] = FALSE;
		$data['voucher_email'] = FALSE;
		$data['voucher_download'] = FALSE;
		$data['voucher_tag'] = $cur_voucher->tag_id;
		$data['voucher_tags'] = $this->Tag_model->get_all_tags();

		/* Load current ledger details if not $_POST */
		if ( ! $_POST)
		{
			$this->db->from('voucher_items')->where('voucher_id', $voucher_id);
			$cur_ledgers_q = $this->db->get();
			if ($cur_ledgers_q->num_rows <= 0)
			{
				$this->messages->add('No Ledger A/C\'s found!', 'error');
			}
			$counter = 0;
			foreach ($cur_ledgers_q->result() as $row)
			{
				$data['ledger_dc'][$counter] = $row->dc;
				$data['ledger_id'][$counter] = $row->ledger_id;
				if ($row->dc == "D")
				{
					$data['dr_amount'][$counter] = $row->amount;
					$data['cr_amount'][$counter] = "";
				} else {
					$data['dr_amount'][$counter] = "";
					$data['cr_amount'][$counter] = $row->amount;
				}
				$counter++;
			}
			/* Two extra rows */
			$data['ledger_dc'][$counter] = 'D';
			$data['ledger_id'][$counter] = 0;
			$data['dr_amount'][$counter] = "";
			$data['cr_amount'][$counter] = "";
			$counter++;
			$data['ledger_dc'][$counter] = 'D';
			$data['ledger_id'][$counter] = 0;
			$data['dr_amount'][$counter] = "";
			$data['cr_amount'][$counter] = "";
			$counter++;
		}

		/* Form validations */
		$this->form_validation->set_rules('voucher_number', 'Voucher Number', 'trim|required|is_natural_no_zero|uniquevouchernowithid[' . v_to_n($voucher_type) . '.' . $voucher_id . ']');
		$this->form_validation->set_rules('voucher_date', 'Voucher Date', 'trim|required|is_date|is_date_within_range');
		$this->form_validation->set_rules('voucher_narration', 'trim');
		$this->form_validation->set_rules('voucher_tag', 'Tag', 'trim|is_natural');

		/* Debit and Credit amount validation */
		if ($_POST)
		{
			foreach ($this->input->post('ledger_dc', TRUE) as $id => $ledger_data)
			{
				$this->form_validation->set_rules('dr_amount[' . $id . ']', 'Debit Amount', 'trim|currency');
				$this->form_validation->set_rules('cr_amount[' . $id . ']', 'Credit Amount', 'trim|currency');
			}
		}

		/* Repopulating form */
		if ($_POST)
		{
			$data['voucher_number']['value'] = $this->input->post('voucher_number', TRUE);
			$data['voucher_date']['value'] = $this->input->post('voucher_date', TRUE);
			$data['voucher_narration']['value'] = $this->input->post('voucher_narration', TRUE);
			$data['voucher_print'] = $this->input->post('voucher_print', TRUE);
			$data['voucher_email'] = $this->input->post('voucher_email', TRUE);
			$data['voucher_download'] = $this->input->post('voucher_download', TRUE);
			$data['voucher_tag'] = $this->input->post('voucher_tag', TRUE);

			$data['ledger_dc'] = $this->input->post('ledger_dc', TRUE);
			$data['ledger_id'] = $this->input->post('ledger_id', TRUE);
			$data['dr_amount'] = $this->input->post('dr_amount', TRUE);
			$data['cr_amount'] = $this->input->post('cr_amount', TRUE);
		}

		if ($this->form_validation->run() == FALSE)
		{
			$this->messages->add(validation_errors(), 'error');
			$this->template->load('template', 'voucher/edit', $data);
		} else	{
			/* Checking for Valid Ledgers A/C and Debit and Credit Total */
			$data_all_ledger_id = $this->input->post('ledger_id', TRUE);
			$data_all_ledger_dc = $this->input->post('ledger_dc', TRUE);
			$data_all_dr_amount = $this->input->post('dr_amount', TRUE);
			$data_all_cr_amount = $this->input->post('cr_amount', TRUE);
			$dr_total = 0;
			$cr_total = 0;
			$bank_cash_present = FALSE; /* Whether atleast one Ledger A/C is Bank or Cash A/C */
			foreach ($data_all_ledger_dc as $id => $ledger_data)
			{
				if ($data_all_ledger_id[$id] < 1)
					continue;

				/* Check for valid ledger id */
				$this->db->from('ledgers')->where('id', $data_all_ledger_id[$id]);
				$valid_ledger_q = $this->db->get();
				if ($valid_ledger_q->num_rows() < 1)
				{
					$this->messages->add('Invalid Ledger A/C.', 'error');
					$this->template->load('template', 'voucher/edit', $data);
					return;
				} else {
					/* Check for valid ledger type */
					$valid_ledger = $valid_ledger_q->row();
					if ($voucher_type == 'receipt')
					{
						if ($data_all_ledger_dc[$id] == 'D' && $valid_ledger->type == 'B')
						{
							$bank_cash_present = TRUE;
						}
					} else if ($voucher_type == 'payment')
					{
						if ($data_all_ledger_dc[$id] == 'C' && $valid_ledger->type == 'B')
						{
							$bank_cash_present = TRUE;
						}
					} else if ($voucher_type == 'contra')
					{
						if ($valid_ledger->type != 'B')
						{
							$this->messages->add('Invalid Ledger A/C. Contra Vouchers can have only Bank and Cash Ledgers A/C\'s.', 'error');
							$this->template->load('template', 'voucher/edit', $data);
							return;
						}
					} else if ($voucher_type == 'journal')
					{
						if ($valid_ledger->type == 'B')
						{
							$this->messages->add('Invalid Ledger A/C. Journal Vouchers cannot have Bank and Cash Ledgers A/C\'s.', 'error');
							$this->template->load('template', 'voucher/edit', $data);
							return;
						}
					}
				}
				if ($data_all_ledger_dc[$id] == "D")
				{
					$dr_total += $data_all_dr_amount[$id];
				} else {
					$cr_total += $data_all_cr_amount[$id];
				}
			}
			if ($dr_total != $cr_total)
			{
				$this->messages->add('Debit and Credit Total does not match!', 'error');
				$this->template->load('template', 'voucher/edit', $data);
				return;
			} else if ($dr_total == 0 && $cr_total == 0) {
				$this->messages->add('Cannot save empty voucher.', 'error');
				$this->template->load('template', 'voucher/edit', $data);
				return;
			}
			/* Check if atleast one Bank or Cash Ledger A/C is present */
			if ($voucher_type == 'receipt')
			{
				if ( ! $bank_cash_present)
				{
					$this->messages->add('Need to Debit atleast one Bank or Cash A/C', 'error');
					$this->template->load('template', 'voucher/edit', $data);
					return;
				}
			} else if ($voucher_type == 'payment')
			{
				if ( ! $bank_cash_present)
				{
					$this->messages->add('Need to Credit atleast one Bank or Cash A/C', 'error');
					$this->template->load('template', 'voucher/edit', $data);
					return;
				}
			}

			/* Updating main voucher */
			$data_number = $this->input->post('voucher_number', TRUE);
			$data_date = $this->input->post('voucher_date', TRUE);
			$data_narration = $this->input->post('voucher_narration', TRUE);
			$data_tag = $this->input->post('voucher_tag', TRUE);
			$data_type = 0;
			switch ($voucher_type)
			{
				case "receipt": $data_type = 1; break;
				case "payment": $data_type = 2; break;
				case "contra": $data_type = 3; break;
				case "journal": $data_type = 4; break;
			}
			$data_date = date_php_to_mysql($data_date); // Converting date to MySQL

			$this->db->trans_start();
			$update_data = array(
				'number' => $data_number,
				'date' => $data_date,
				'narration' => $data_narration,
				'tag_id' => $data_tag,
			);
			if ( ! $this->db->where('id', $voucher_id)->update('vouchers', $update_data))
			{
				$this->db->trans_rollback();
				$this->messages->add('Error updating Voucher A/C.', 'error');
				$this->logger->write_message("error", "Error updating voucher details for " . ucfirst($voucher_type) . " Voucher number " . voucher_number_prefix($voucher_type) . $data_number . " [id:" . $voucher_id . "]");
				$this->template->load('template', 'voucher/edit', $data);
				return;
			}

			/* TODO : Deleting all old ledger data, Bad solution */
			if ( ! $this->db->delete('voucher_items', array('voucher_id' => $voucher_id)))
			{
				$this->db->trans_rollback();
				$this->messages->add('Error deleting old Ledger A/C\'s', 'error');
				$this->logger->write_message("error", "Error updating old voucher items for " . ucfirst($voucher_type) . " Voucher number " . voucher_number_prefix($voucher_type) . $data_number . " [id:" . $voucher_id . "]");
				$this->template->load('template', 'voucher/edit', $data);
				return;
			}
			
			/* Adding ledger accounts */
			$data_all_ledger_dc = $this->input->post('ledger_dc', TRUE);
			$data_all_ledger_id = $this->input->post('ledger_id', TRUE);
			$data_all_dr_amount = $this->input->post('dr_amount', TRUE);
			$data_all_cr_amount = $this->input->post('cr_amount', TRUE);

			$dr_total = 0;
			$cr_total = 0;
			foreach ($data_all_ledger_dc as $id => $ledger_data)
			{
				$data_ledger_dc = $data_all_ledger_dc[$id];
				$data_ledger_id = $data_all_ledger_id[$id];
				if ($data_ledger_id < 1)
					continue;
				$data_amount = 0;
				if ($data_all_ledger_dc[$id] == "D")
				{
					$data_amount = $data_all_dr_amount[$id];
					$dr_total += $data_all_dr_amount[$id];
				} else {
					$data_amount = $data_all_cr_amount[$id];
					$cr_total += $data_all_cr_amount[$id];
				}

				$insert_ledger_data = array(
					'voucher_id' => $voucher_id,
					'ledger_id' => $data_ledger_id,
					'amount' => $data_amount,
					'dc' => $data_ledger_dc,
				);
				if ( ! $this->db->insert('voucher_items', $insert_ledger_data))
				{
					$this->db->trans_rollback();
					$this->messages->add('Error updating Ledger A/C ' . $data_ledger_id . '.', 'error');
					$this->logger->write_message("error", "Error updating new voucher item [id:" . $data_ledger_id . "] for " . ucfirst($voucher_type) . " Voucher number " . voucher_number_prefix($voucher_type) . $data_number . " [id:" . $voucher_id . "]");
					$this->template->load('template', 'voucher/edit', $data);
					return;
				}
			}

			/* Updating Debit and Credit Total in vouchers table */
			$update_data = array(
				'dr_total' => $dr_total,
				'cr_total' => $cr_total,
			);
			if ( ! $this->db->where('id', $voucher_id)->update('vouchers', $update_data))
			{
				$this->db->trans_rollback();
				$this->messages->add('Error updating voucher total.', 'error');
				$this->logger->write_message("error", "Error updating voucher total for " . ucfirst($voucher_type) . " Voucher number " . voucher_number_prefix($voucher_type) . $data_number . " [id:" . $voucher_id . "]");
				$this->template->load('template', 'voucher/edit', $data);
				return;
			}

			/* Success */
			$this->db->trans_complete();

			/* Check for Voucher Print, Download, Email */
			if ($this->input->post('voucher_print', TRUE))
			{
				$this->session->set_userdata('print_voucher', TRUE);
				$this->session->set_userdata('print_voucher_type', strtolower($voucher_type));
				$this->session->set_userdata('print_voucher_id', $voucher_id);
			}
			if ($this->input->post('voucher_email', TRUE))
			{
				$this->session->set_userdata('email_voucher', TRUE);
				$this->session->set_userdata('email_voucher_type', strtolower($voucher_type));
				$this->session->set_userdata('email_voucher_id', $voucher_id);
			}
			if ($this->input->post('voucher_download', TRUE))
			{
				$this->session->set_userdata('download_voucher', TRUE);
				$this->session->set_userdata('download_voucher_type', strtolower($voucher_type));
				$this->session->set_userdata('download_voucher_id', $voucher_id);
			}

			/* Voucher Actions */
			$voucher_success_links = "You can ";
			$voucher_success_links .= anchor('voucher/view/' . strtolower($voucher_type) . "/" . $voucher_id, 'View', array('class' => 'anchor-link-a')) . " or ";
			$voucher_success_links .= anchor('voucher/download/' . strtolower($voucher_type) . "/" . $voucher_id, 'Download', array('class' => 'anchor-link-a'));
			$voucher_success_links .= " it.";

			$this->messages->add('Updated ' . ucfirst($voucher_type) . ' Voucher number ' . voucher_number_prefix($voucher_type) . $data_number . ". " . $voucher_success_links, 'success');
			$this->logger->write_message("success", "Updated " . ucfirst($voucher_type) . " Voucher number " . voucher_number_prefix($voucher_type) . $data_number . " [id:" . $voucher_id . "]");
			redirect('voucher/show/' . $voucher_type);
			return;
		}
		return;
	}

	function delete($voucher_type, $voucher_id)
	{
		/* Check access */
		if ( ! check_access('delete voucher'))
		{
			$this->messages->add('Permission denied.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}

		/* Check for account lock */
		if ($this->config->item('account_locked') == 1)
		{
			$this->messages->add('Account is locked.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}

		/* Load current voucher details */
		if ( ! $cur_voucher = $this->Voucher_model->get_voucher($voucher_id, $voucher_type))
		{
			$this->messages->add('Invalid Voucher number.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}

		$this->db->trans_start();
		if ( ! $this->db->delete('voucher_items', array('voucher_id' => $voucher_id)))
		{
			$this->db->trans_rollback();
			$this->messages->add('Error deleting Voucher - Ledgers entry.', 'error');
			$this->logger->write_message("error", "Error deleting ledger entries for " . ucfirst($voucher_type) . " Voucher number " . voucher_number_prefix($voucher_type) . $cur_voucher->number . " [id:" . $voucher_id . "]");
			redirect('voucher/' . $voucher_type . '/' . $voucher_id);
			return;
		}
		if ( ! $this->db->delete('vouchers', array('id' => $voucher_id)))
		{
			$this->db->trans_rollback();
			$this->messages->add('Error deleting Voucher entry.', 'error');
			$this->logger->write_message("error", "Error deleting voucher entry for " . ucfirst($voucher_type) . " Voucher number " . voucher_number_prefix($voucher_type) . $cur_voucher->number . " [id:" . $voucher_id . "]");
			redirect('voucher/' . $voucher_type . '/' . $voucher_id);
			return;
		}
		$this->db->trans_complete();
		$this->messages->add('Deleted ' .  ucfirst($voucher_type) . ' Voucher.', 'success');
		$this->logger->write_message("success", "Deleted " . ucfirst($voucher_type) . " Voucher number " . voucher_number_prefix($voucher_type) . $cur_voucher->number . " [id:" . $voucher_id . "]");
		redirect('voucher/show/' . $voucher_type);
		return;
	}

	function download($voucher_type, $voucher_id = 0)
	{
		$this->load->helper('download');
		$this->load->model('Setting_model');
		$this->load->model('Ledger_model');

		/* Check access */
		if ( ! check_access('download voucher'))
		{
			$this->messages->add('Permission denied.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}

		$account = $this->Setting_model->get_current();

		/* Load current voucher details */
		if ( ! $cur_voucher = $this->Voucher_model->get_voucher($voucher_id, $voucher_type))
		{
			$this->messages->add('Invalid Voucher number.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}

		$data['voucher_type'] = $voucher_type;
		$data['voucher_number'] =  $cur_voucher->number;
		$data['voucher_date'] = date_mysql_to_php_display($cur_voucher->date);
		$data['voucher_dr_total'] =  $cur_voucher->dr_total;
		$data['voucher_cr_total'] =  $cur_voucher->cr_total;
		$data['voucher_narration'] = $cur_voucher->narration;

		/* Getting Ledger details */
		$this->db->from('voucher_items')->where('voucher_id', $voucher_id)->order_by('dc', 'desc');
		$ledger_q = $this->db->get();
		$counter = 0;
		$data['ledger_data'] = array();
		if ($ledger_q->num_rows() > 0)
		{
			foreach ($ledger_q->result() as $row)
			{
				$data['ledger_data'][$counter] = array(
					'id' => $row->ledger_id,
					'name' => $this->Ledger_model->get_name($row->ledger_id),
					'dc' => $row->dc,
					'amount' => $row->amount,
				);
				$counter++;
			}
		}

		/* Download Voucher */
		$file_name = $voucher_type . '_voucher_' . $cur_voucher->number . ".html";
		$download_data = $this->load->view('voucher/downloadpreview', $data, TRUE);
		force_download($file_name, $download_data);
		return;
	}

	function printpreview($voucher_type, $voucher_id)
	{
		$this->load->model('Setting_model');
		$this->load->model('Ledger_model');

		/* Check access */
		if ( ! check_access('print voucher'))
		{
			$this->messages->add('Permission denied.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}

		$account = $this->Setting_model->get_current();

		/* Load current voucher details */
		if ( ! $cur_voucher = $this->Voucher_model->get_voucher($voucher_id, $voucher_type))
		{
			$this->messages->add('Invalid Voucher number.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}

		$data['voucher_type'] = $voucher_type;
		$data['voucher_number'] =  $cur_voucher->number;
		$data['voucher_date'] = date_mysql_to_php_display($cur_voucher->date);
		$data['voucher_dr_total'] =  $cur_voucher->dr_total;
		$data['voucher_cr_total'] =  $cur_voucher->cr_total;
		$data['voucher_narration'] = $cur_voucher->narration;

		/* Getting Ledger details */
		$this->db->from('voucher_items')->where('voucher_id', $voucher_id)->order_by('dc', 'desc');
		$ledger_q = $this->db->get();
		$counter = 0;
		$data['ledger_data'] = array();
		if ($ledger_q->num_rows() > 0)
		{
			foreach ($ledger_q->result() as $row)
			{
				$data['ledger_data'][$counter] = array(
					'id' => $row->ledger_id,
					'name' => $this->Ledger_model->get_name($row->ledger_id),
					'dc' => $row->dc,
					'amount' => $row->amount,
				);
				$counter++;
			}
		}

		$this->load->view('voucher/printpreview', $data);
		return;
	}

	function email($voucher_type, $voucher_id)
	{
		$this->load->model('Setting_model');
		$this->load->model('Ledger_model');
		$this->load->library('email');

		/* Check access */
		if ( ! check_access('email voucher'))
		{
			$this->messages->add('Permission denied.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}

		$account_data = $this->Setting_model->get_current();

		/* Load current voucher details */
		if ( ! $cur_voucher = $this->Voucher_model->get_voucher($voucher_id, $voucher_type))
		{
			$this->messages->add('Invalid Voucher number.', 'error');
			redirect('voucher/show/' . $voucher_type);
			return;
		}

		$data['voucher_type'] = $voucher_type;
		$data['voucher_id'] = $voucher_id;
		$data['email_to'] = array(
			'name' => 'email_to',
			'id' => 'email_to',
			'size' => '40',
			'value' => '',
		);

		/* Form validations */
		$this->form_validation->set_rules('email_to', 'Email to', 'trim|valid_emails|required');

		/* Repopulating form */
		if ($_POST)
		{
			$data['email_to']['value'] = $this->input->post('email_to', TRUE);
		}

		if ($this->form_validation->run() == FALSE)
		{
			$data['error'] = validation_errors();
			$this->load->view('voucher/email', $data);
			return;
		}
		else
		{
			$voucher_data['voucher_type'] = $voucher_type;
			$voucher_data['voucher_number'] =  $cur_voucher->number;
			$voucher_data['voucher_date'] = date_mysql_to_php_display($cur_voucher->date);
			$voucher_data['voucher_dr_total'] =  $cur_voucher->dr_total;
			$voucher_data['voucher_cr_total'] =  $cur_voucher->cr_total;
			$voucher_data['voucher_narration'] = $cur_voucher->narration;
	
			/* Getting Ledger details */
			$this->db->from('voucher_items')->where('voucher_id', $voucher_id)->order_by('dc', 'desc');
			$ledger_q = $this->db->get();
			$counter = 0;
			$voucher_data['ledger_data'] = array();
			if ($ledger_q->num_rows() > 0)
			{
				foreach ($ledger_q->result() as $row)
				{
					$voucher_data['ledger_data'][$counter] = array(
						'id' => $row->ledger_id,
						'name' => $this->Ledger_model->get_name($row->ledger_id),
						'dc' => $row->dc,
						'amount' => $row->amount,
					);
					$counter++;
				}
			}

			/* Preparing message */
			$message = $this->load->view('voucher/emailpreview', $voucher_data, TRUE);

			/* Getting email configuration */
			$config['smtp_timeout'] = '30';
			$config['charset'] = 'utf-8';
			$config['newline'] = "\r\n";
			$config['mailtype'] = "html";
			if ($account_data)
			{
				$config['protocol'] = $account_data->email_protocol;
				$config['smtp_host'] = $account_data->email_host;
				$config['smtp_port'] = $account_data->email_port;
				$config['smtp_user'] = $account_data->email_username;
				$config['smtp_pass'] = $account_data->email_password;
			} else {
				$data['error'] = 'Invalid account details.';
			}
			$this->email->initialize($config);

			/* Sending email */
			$this->email->from('', 'Webzash');
			$this->email->to($this->input->post('email_to', TRUE));
			$this->email->subject(ucfirst($voucher_type) . ' Voucher No. ' . voucher_number_prefix($voucher_type) . $cur_voucher->number);
			$this->email->message($message);
			if ($this->email->send())
			{
				$data['message'] = "Email sent.";
				$this->logger->write_message("success", "Emailed " . ucfirst($voucher_type) . " Voucher number " . voucher_number_prefix($voucher_type) . $cur_voucher->number . " [id:" . $voucher_id . "]");
			} else {
				$data['error'] = "Error sending email. Check you email settings.";
				$this->logger->write_message("error", "Error emailing " . ucfirst($voucher_type) . " Voucher number " . voucher_number_prefix($voucher_type) . $cur_voucher->number . " [id:" . $voucher_id . "]");
			}
			$this->load->view('voucher/email', $data);
			return;
		}
		return;
	}

	function addrow($add_type = 'all')
	{
		$i = time() + rand  (0, time()) + rand  (0, time()) + rand  (0, time());
		$dr_amount = array(
			'name' => 'dr_amount[' . $i . ']',
			'id' => 'dr_amount[' . $i . ']',
			'maxlength' => '15',
			'size' => '15',
			'value' => '',
			'class' => 'dr-item',
			'disabled' => 'disabled',
		);
		$cr_amount = array(
			'name' => 'cr_amount[' . $i . ']',
			'id' => 'cr_amount[' . $i . ']',
			'maxlength' => '15',
			'size' => '15',
			'value' => '',
			'class' => 'cr-item',
			'disabled' => 'disabled',
		);

		echo '<tr class="new-row">';
		echo '<td>';
		echo form_dropdown_dc('ledger_dc[' . $i . ']');
		echo '</td>';

		echo '<td>';
		if ($add_type == 'bankcash')
			echo form_input_ledger('ledger_id[' . $i . ']', 0, '', $type = 'bankcash');
		else if ($add_type == 'nobankcash')
			echo form_input_ledger('ledger_id[' . $i . ']', 0, '', $type = 'nobankcash');
		else
			echo form_input_ledger('ledger_id[' . $i . ']');
		echo '</td>';

		echo '<td>';
		echo form_input($dr_amount);
		echo '</td>';
		echo '<td>';
		echo form_input($cr_amount);
		echo '</td>';
		echo '<td>';
		echo img(array('src' => asset_url() . "images/icons/add.png", 'border' => '0', 'alt' => 'Add Ledger', 'class' => 'addrow'));
		echo '</td>';
		echo '<td>';
		echo img(array('src' => asset_url() . "images/icons/delete.png", 'border' => '0', 'alt' => 'Remove Ledger', 'class' => 'deleterow'));
		echo '</td>';
		echo '<td class="ledger-balance"><div></div>';
		echo '</td>';
		echo '</tr>';
		return;
	}
}

/* End of file voucher.php */
/* Location: ./system/application/controllers/voucher.php */
