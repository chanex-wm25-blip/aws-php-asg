resource "aws_sns_topic" "alerts" {
  name = "${var.name_prefix}-alerts"

  tags = {
    Name = "${var.name_prefix}-alerts"
  }
}

resource "aws_sns_topic_subscription" "email" {
  topic_arn = aws_sns_topic.alerts.arn
  protocol  = "email"
  endpoint  = var.alert_email
}

resource "aws_cloudwatch_metric_alarm" "asg_high_cpu" {
  alarm_name          = "${var.name_prefix}-asg-high-cpu"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 3

  metric_name = "CPUUtilization"
  namespace   = "AWS/EC2"

  period    = 60
  statistic = "Average"
  threshold = 85

  alarm_description = "ASG average CPU is above 85% for 3 consecutive minutes."

  dimensions = {
    AutoScalingGroupName = var.asg_name
  }

  alarm_actions = [aws_sns_topic.alerts.arn]
  ok_actions    = [aws_sns_topic.alerts.arn]
}

resource "aws_cloudwatch_metric_alarm" "alb_5xx_errors" {
  alarm_name          = "${var.name_prefix}-alb-5xx-errors"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 2

  metric_name = "HTTPCode_ELB_5XX_Count"
  namespace   = "AWS/ApplicationELB"

  period    = 60
  statistic = "Sum"
  threshold = 10

  alarm_description = "ALB returned more than 10 HTTP 5xx errors in one minute."

  alarm_actions = [aws_sns_topic.alerts.arn]
  ok_actions    = [aws_sns_topic.alerts.arn]

  dimensions = {
    LoadBalancer = var.alb_arn_suffix
  }
}

resource "aws_cloudwatch_metric_alarm" "asg_low_instances" {
  alarm_name          = "${var.name_prefix}-asg-low-instances"
  comparison_operator = "LessThanThreshold"
  evaluation_periods  = 2

  metric_name = "GroupInServiceInstances"
  namespace   = "AWS/AutoScaling"

  period    = 60
  statistic = "Average"
  threshold = 2

  alarm_description = "ASG has fewer than 2 instances in service."

  alarm_actions = [aws_sns_topic.alerts.arn]
  ok_actions    = [aws_sns_topic.alerts.arn]

  dimensions = {
    AutoScalingGroupName = var.asg_name
  }
}