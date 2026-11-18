class ProviderWallet {
  final double outstandingCodCommissionDebt; // Account 1030 (Asset - debt owed)
  final double earnedPayables; // Account 2020 (Liability - earnings payable)
  final double netWithdrawableBalance;
  final double codDebtThreshold;
  final bool isDebtBlocked;
  final bool canAcceptJobs;

  ProviderWallet({
    required this.outstandingCodCommissionDebt,
    required this.earnedPayables,
    required this.netWithdrawableBalance,
    required this.codDebtThreshold,
    required this.isDebtBlocked,
    required this.canAcceptJobs,
  });

  factory ProviderWallet.fromJson(Map<String, dynamic> json) {
    return ProviderWallet(
      outstandingCodCommissionDebt: (json['outstanding_cod_commission_debt'] as num?)?.toDouble() ?? 0.0,
      earnedPayables: (json['earned_payables'] as num?)?.toDouble() ?? 0.0,
      netWithdrawableBalance: (json['net_withdrawable_balance'] as num?)?.toDouble() ?? 0.0,
      codDebtThreshold: (json['cod_debt_threshold'] as num?)?.toDouble() ?? 2000.0,
      isDebtBlocked: json['is_debt_blocked'] ?? false,
      canAcceptJobs: json['can_accept_jobs'] ?? true,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'outstanding_cod_commission_debt': outstandingCodCommissionDebt,
      'earned_payables': earnedPayables,
      'net_withdrawable_balance': netWithdrawableBalance,
      'cod_debt_threshold': codDebtThreshold,
      'is_debt_blocked': isDebtBlocked,
      'can_accept_jobs': canAcceptJobs,
    };
  }
}
