if (typeof(quizChartData) != 'undefined'){
    console.log(quizChartData);  // debug
    
    for (var i=0; i<quizChartData.length; i++) {
        console.log(quizChartData[i]);  // debug
        
        var chartData = quizChartData[i];
        var svgHeight = 400;
        var svgWidth  = 500;
        
        var marginTop = 40;
        var marginBottom = 60;
        var marginLeft = 40;
        var marginRight = 30;
        
        var chartHeight = svgHeight - marginTop - marginBottom;
        var chartWidth  = svgWidth - marginLeft - marginRight;
        
        
        var maxVal = parseInt(chartData.maxVal);
        
        var maxValue = d3.max(chartData.data.participants, function(d, i){
            return d.y;
        });
        
        var yScale = d3.scale.linear()
                       .domain([0, maxVal])
                       .range([chartHeight, marginLeft]);
        
        // plot histogram bar
        d3.select('#quizchart-' + i)
          .selectAll('rect')
          .data(chartData.data.participants)
          .enter()
          .append('rect')
          .attr('class', 'bar')
          .style('fill', 'red')
          .style('stroke', 'none')
          .attr('height', function(d, i){
              return  d * (chartHeight-marginTop) / maxVal;
          })
          .attr('width', 20)
          .attr('x', function(d, i){
              return i * chartWidth / chartData.data.bandlabels.length + marginLeft;
          })
          .attr('y', function(d, i){
              return chartHeight - d * (chartHeight-marginTop) / maxVal;
          })
          
        // plot Y axis
        d3.select('#quizchart-' + i)
          .append('g')
          .attr('class', 'axis')
          .attr('transform', 'translate(' + marginLeft + ', 0)')
          .call(
              d3.svg.axis()
                .scale(yScale)
                .orient('left')
                .innerTickSize(-chartWidth)
                .outerTickSize(0)
                .tickPadding(10)
          )
          
        
        // plot X axis
        d3.select('#quizchart-' + i)
          .append('g')
          .attr('class', 'axis')
          .attr('transform', 'translate(' + marginLeft + ', ' + chartHeight + ')')
          .call(
              d3.svg.axis().scale(
                d3.scale.linear().range([0, chartWidth])
              )
              .orient('middle')
              .ticks(chartData.data.bandlabels.length)
              .tickFormat(function(d, i){
                  return chartData.data.bandlabels[i]
              })
          )
          .selectAll('text')
          .style('text-anchor', 'middle')
          .attr('transform', 'rotate(-45)')
          .attr("dx", "-1.5em")
          .attr("dy", "1.25em")
          
        // X axis title
        d3.select('#quizchart-' + i)
          .append('text')
          .attr("x", chartWidth / 2 + marginLeft)
          .attr('y', chartHeight + 60)
          .style("text-anchor", "middle")
          .text(chartData.lang.grade);
        
        // Y axis title
        d3.select('#quizchart-' + i)
          .append('text')
          .attr('transform', 'rotate(-90)')
          .attr("x", 0 - chartHeight / 2 - marginTop)
          .attr('y', 10)
          .style("text-anchor", "middle")
          .text(chartData.lang.participants);
    }
}
